<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransactionController extends Controller
{
    use MessageHandler;
    /**
     * Send money to another user.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'to_phone' => 'required|exists:users,phone',
            'amount'   => 'required|numeric|min:1',
            'pin'      => 'required|digits:4',
        ]);

        $fromUser = authUser($request);

        // PIN check
        if (! Hash::check($request->pin, $fromUser->pin)) {
            return response()->json([
                'code'    => 'INVALID_PIN',
                'message' => 'Incorrect wallet pin',
            ], 401);
        }

        $toUser = User::where('phone', $validated['to_phone'])->first();

        // check same user
        if ($fromUser->id == $toUser->id) {
            return response()->json([
                'code'    => 'SAME_USER',
                'message' => 'Couldn\'t process the transaction against same ID',
            ], 400);
        }

        // check user role
        if ($toUser->role !== 'user') {
            return response()->json([
                'code'    => 'ROLE_RESTRICTED',
                'message' => "You couldn't send your money to any {$toUser->role}.",
            ], 400);
        }

        // check user kyc
        if (! $fromUser->kyc || $fromUser->kyc->status !== 'approved') {
            return response()->json([
                'code'    => 'KYC_NOT_VERIFIED',
                'message' => 'Please update your KYC to make your transaction',
            ], 400);
        }

        // check same amount transfer under 2 minutes
        $lastTransaction = Transaction::where('from_user_id', $fromUser->id)
            ->where('to_user_id', $toUser->id)
            ->where('amount', $request->amount)
            ->where('created_at', '>=', Carbon::now()->subMinutes(2))
            ->first();

        if ($lastTransaction) {
            return response()->json([
                'code'    => 'FREQUENT_TRANSACTION',
                'message' => 'You cannot send the same amount to the same user within 2 minutes.',
            ], 400);
        }

        // check balance
        if ($fromUser->wallet->balance < $request->amount) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance',
            ], 302);
        }

        // Check balance & transfer in DB transaction
        DB::beginTransaction();
        try {
            $fromWallet = $fromUser->wallet;
            $toWallet   = $toUser->wallet;

            if ($fromWallet->balance < $validated['amount']) {
                return response()->json(['message' => 'Insufficient balance'], 400);
            }

            // Debit sender
            $fromWallet->balance -= $validated['amount'];
            $fromWallet->save();

            // Credit receiver
            $toWallet->balance += $validated['amount'];
            $toWallet->save();

            // Log transactions
            $transaction = Transaction::create([
                'from_user_id' => $fromUser->id,
                'to_user_id'   => $toUser->id,
                'amount'       => $validated['amount'],
                'type'         => 'transfer',
                'status'       => 'completed',
                'txn_id'       => uniqid(),
            ]);

            $app = config('app.name');

            DB::commit();

            // masking phone numbers for privacy
            $userMask = maskPhone($toUser->phone);
            $senderMask = maskPhone($fromUser->phone);

            // Notify users via SMS
            $this->smsInit("You have received Tk{$request->amount} from {$senderMask} TxID:{$transaction->txn_id} Your new balance is Tk{$toUser->wallet->balance}. Thanks for using {$app}.", "Received money {$request->amount} ", $toUser->phone, null, $toUser->name);

            // Notify sender via SMS
            $this->smsInit("You have sent Tk{$request->amount} to {$userMask} TxID:{$transaction->txn_id} Your new balance is Tk{$fromUser->wallet->balance}. Thanks for using {$app}.", "Send money {$request->amount} ", $fromUser->phone, null, $fromUser->name);

            // Return success response
            return response()->json([
                'code'    => 'SEND_MONEY',
                'message' => "Send money Tk{$request->amount} transaction has been completed.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transfer failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Agent cash-out from user's wallet.
     */
    public function cashOut(Request $request)
    {
        $validated = $request->validate([
            'user_phone' => 'required|exists:users,phone',
            'amount'     => 'required|numeric|min:1',
            'pin'        => 'required|digits:4',
        ]);

        $agent = User::where('phone', $validated['user_phone'])->first();
        $user  = authUser($request);

        // check pin
        if (! Hash::check($request->pin, $agent->pin)) {
            return response()->json([
                'code'    => 'INVALID_PIN',
                'message' => 'Incorrect wallet pin',
            ], 401);
        }

        // kyc check
        if (! $user->kyc || $user->kyc->status !== 'approved') {
            return response()->json([
                'code'    => 'KYC_NOT_VERIFIED',
                'message' => 'Please update your KYC to make your transaction',
            ], 400);
        }

        // check balance
        if ($user->wallet->balance < $request->amount) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance',
            ], 302);
        }

        try {
            DB::beginTransaction();

            // added money in user wallet
            $agent->wallet->balance += $validated['amount'];
            $agent->wallet->save();

            // sub-struct money from agent wallet
            $user->wallet->balance -= $validated['amount'];
            $user->wallet->save();

            // Log transaction, track agent
            $transaction = Transaction::create([
                'from_user_id' => $user->id, // agent performed cash-in
                'to_user_id'   => $agent->id,
                'amount'       => $validated['amount'],
                'type'         => 'cash_out',
                'status'       => 'completed',
                'txn_id'       => uniqid(),
            ]);
            DB::commit();
            // user message
            $this->smsInit("You have successfully cashed out Tk{$request->amount} to {$agent->phone} TxID:{$transaction->txn_id} Your new balance is Tk{$user->wallet->balance}", "Cash-out {$request->amount} ", $user->phone, null, $user->name);

            // agent message
            $this->smsInit("Cashed out credited Tk{$request->amount} from {$user->phone} successful TxID:{$transaction->txn_id} Your new balance is Tk{$agent->wallet->balance}", "Cash-out {$request->amount} ", $agent->phone, null, $agent->name);

            return response()->json([
                'code'    => 'CASH_OUT',
                'message' => 'Cash-out successful',
            ], 200);
        } catch (\Throwable $e) {
        }
    }

    // user transaction summery
    public function summery(Request $request)
    {
        $user = authUser($request);
        $send = Transaction::where('from_user_id', $user->id)
            ->where('type', 'transfer')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $receive = Transaction::where('to_user_id', $user->id)
            ->where('type', 'transfer')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $cashIn = Transaction::where('to_user_id', $user->id)
            ->where('type', 'cash_in')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $cashOut = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $payment = Transaction::where('from_user_id', $user->id)
            ->where('type', 'payment')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $agentCashIn = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_in')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $agentCashOut = Transaction::where('to_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $merchantCashOut = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $merchantPayment = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'completed')->sum('amount');

        $transaction = Transaction::where('from_user_id', $user->id)
            ->orWhere('to_user_id', $user->id)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->with([
                'fromUser' => function ($from) {
                    $from->select('id', 'name', 'phone', 'image', 'role');
                },
                'toUser'   => function ($to) {
                    $to->select('id', 'name', 'phone', 'image', 'role');
                }
            ])
            ->latest()
            ->limit(10)
            ->get();


        $transfer = Transaction::where('from_user_id', $user->id)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->select('to_user_id', DB::raw('MAX(id) as id'))
            ->groupBy('to_user_id')
            ->orderByDesc('id')
            ->limit(10)
            ->with([
                'toUser' => function ($to) {
                    $to->select('id', 'name', 'phone', 'image', 'role');
                }
            ])
            ->get();

        // users transactions summery
        return response()->json([
            'send'              => $send,
            'receive'           => $receive,
            'transactions'      => $transaction,
            'balance'           => $user->wallet->balance,
            'month'             => Carbon::now()->format('F Y'),
            'cash_in'          => $cashIn,
            'cash_out'         => $cashOut,
            'payment'          => $payment,
            'transfer'         => $transfer,
            'agent_cash_in'    => $agentCashIn,
            'agent_cash_out'   => $agentCashOut,
            'merchant_cash_out' => $merchantCashOut,
            'merchant_payment' => $merchantPayment,
        ]);
    }
}
