<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionCharge;
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

        $charge = TransactionCharge::where('user_id',$fromUser->id)->first();

        // check amount charge
        $amount = $validated['amount'];
        $chargeAmount = ($amount * $charge->cash_out_percentage / 100);
        $request->merge(['amount' => $amount + $chargeAmount]);

        // check daily and monthly limits
        $dailyLimit = $fromUser->transactionLimit->daily_cash_out_limit;
        $monthlyLimit = $fromUser->transactionLimit->monthly_cash_out_limit;
        $dailyTotal = Transaction::where('from_user_id', $fromUser->id)
            ->where('type', 'cash_out')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        $monthlyTotal = Transaction::where('from_user_id', $fromUser->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');


        if (($dailyTotal + $request->amount) > $dailyLimit) {
            return response()->json([
                'code'    => 'DAILY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your daily send money limit of Tk{$dailyLimit}.",
            ], 400);
        }

        if (($monthlyTotal + $request->amount) > $monthlyLimit) {
            return response()->json([
                'code'    => 'MONTHLY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your monthly send money limit of Tk{$monthlyLimit}.",
            ], 400);
        }

        // check cash-out max limit
        if ($request->amount > $fromUser->transactionLimit->cash_out_max) {
            return response()->json([
                'code'    => 'TRANSFER_MAX_LIMIT',
                'message' => "You cannot send money more than Tk{$fromUser->transactionLimit->cash_out_max}.",
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

            $date = Carbon::parse($transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');

            // masking phone numbers for privacy
            $userMask = maskPhone($toUser->phone);
            $senderMask = maskPhone($fromUser->phone);

            // Notify users via SMS
            $this->smsInit("You have received Tk{$request->amount} from {$senderMask} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$toUser->wallet->balance}. Thanks for using {$app}.", "Received money {$request->amount} ", $toUser->phone, null, $toUser->name);

            // Notify sender via SMS
            $this->smsInit("You have sent Tk{$request->amount} to {$userMask} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$fromUser->wallet->balance}. Thanks for using {$app}.", "Send money {$request->amount} ", $fromUser->phone, null, $fromUser->name);

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

        $charge = TransactionCharge::where('user_id',$user->id)->first();

        // check pin
        if (! Hash::check($request->pin, $user->pin)) {
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

        // check amount charge
        $amount = $validated['amount'];
        $chargeAmount = ($amount * $charge->cash_out_percentage / 100);
        $request->merge(['amount' => $amount + $chargeAmount]);

        // check daily and monthly limits
        $dailyLimit = $user->transactionLimit->daily_cash_out_limit;
        $monthlyLimit = $user->transactionLimit->monthly_cash_out_limit;
        $dailyTotal = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        $monthlyTotal = Transaction::where('from_user_id', $user->id)
            ->where('type', 'cash_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');


        if (($dailyTotal + $request->amount) > $dailyLimit) {
            return response()->json([
                'code'    => 'DAILY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your daily cash-out limit of Tk{$dailyLimit}.",
            ], 400);
        }

        if (($monthlyTotal + $request->amount) > $monthlyLimit) {
            return response()->json([
                'code'    => 'MONTHLY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your monthly cash-out limit of Tk{$monthlyLimit}.",
            ], 400);
        }

        // check cash-out max limit
        if ($request->amount > $user->transactionLimit->cash_out_max) {
            return response()->json([
                'code'    => 'CASH_OUT_MAX_LIMIT',
                'message' => "You cannot cash out more than Tk{$user->transactionLimit->cash_out_max}.",
            ], 400);
        }

        // check balance
        if ($user->wallet->balance < $request->amount) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance',
            ], 302);
        }

        $toUser = User::where('phone', $validated['user_phone'])->first();

        // check same user
        if ($user->id == $toUser->id) {
            return response()->json([
                'code'    => 'SAME_USER',
                'message' => 'Couldn\'t process the transaction against same user',
            ], 400);
        }

        // check same amount transfer under 2 minutes
        $lastTransaction = Transaction::where('from_user_id', $user->id)
            ->where('to_user_id', $toUser->id)
            ->where('amount', $validated['amount'])
            ->where('created_at', '>=', Carbon::now()->subMinutes(2))
            ->first();

        if ($lastTransaction) {
            return response()->json([
                'code'    => 'FREQUENT_TRANSACTION',
                'message' => 'You cannot cash out the same amount to the same agent within 2 minutes.',
            ], 400);
        }

        // check user role
        if ($toUser->role !== 'agent') {
            return response()->json([
                'code'    => 'ROLE_RESTRICTED',
                'message' => "You couldn't cash out to any {$toUser->role}.",
            ], 400);
        }

        // agent interest
        $interest = number_format(($chargeAmount / 2), 2);

        try {
            DB::beginTransaction();

            // added money in user wallet
            $agent->wallet->balance += ( $request->amount - $interest);
            $agent->wallet->save();

            // sub-struct money from agent wallet
            $user->wallet->balance -= $request->amount;
            $user->wallet->save();

            // Log transaction, track agent
            $transaction = Transaction::create([
                'from_user_id' => $user->id, // agent performed cash-in
                'to_user_id'   => $agent->id,
                'amount'       => $validated['amount'],
                'type'         => 'cash_out',
                'status'       => 'completed',
                'txn_id'       => uniqid(),
                'charge_amount' => $chargeAmount,
                'interest'     => $interest,
            ]);
            DB::commit();

            $date = Carbon::parse($transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');

            // masking phone numbers for privacy
            $userMask = maskPhone($toUser->phone);
            $agentMask = maskPhone($agent->phone);

            // make number formatting
            $amount = number_format($validated['amount'], 2);
            $chargeAmount = number_format($chargeAmount, 2);


            // user message
            $this->smsInit("You have successfully cash out Tk{$amount} to {$agentMask} Fee Tk{$chargeAmount} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$user->wallet->balance}", "Cash-out {$amount} ", $user->phone, null, $user->name);

            // agent message
            $this->smsInit("Cash out credited Tk{$amount} from {$user->phone} successful interest Fee Tk{$interest} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$agent->wallet->balance}", "Cash-out {$amount} ", $agent->phone, null, $agent->name);

            return response()->json([
                'code'    => 'CASH_OUT',
                'message' => 'Cash-out successful',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Cash-out failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Merchant payment from user's wallet.
     */
    public function onlinePayment(Request $request)
    {
        $validated = $request->validate([
            'user_phone' => 'required|exists:users,phone',
            'amount'     => 'required|numeric|min:1',
            'pin'        => 'required|digits:4',
        ]);


        $merchant = User::where('phone', $validated['user_phone'])->first();
        $user  = authUser($request);

        $charge = TransactionCharge::where('user_id',$user->id)->first();

        // check pin
        if (! Hash::check($request->pin, $user->pin)) {
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

        // check amount charge
        $amount = $validated['amount'];
        $chargeAmount = ($amount * $charge->payment_percentage / 100);
        $request->merge(['amount' => $amount + $chargeAmount]);

        // check daily and monthly limits
        $dailyLimit = $user->transactionLimit->daily_payment_limit;
        $monthlyLimit = $user->transactionLimit->monthly_payment_limit;
        $dailyTotal = Transaction::where('from_user_id', $user->id)
            ->where('type', 'payment')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        $monthlyTotal = Transaction::where('from_user_id', $user->id)
            ->where('type', 'payment')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');


        if (($dailyTotal + $request->amount) > $dailyLimit) {
            return response()->json([
                'code'    => 'DAILY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your daily payment limit of Tk{$dailyLimit}.",
            ], 400);
        }

        if (($monthlyTotal + $request->amount) > $monthlyLimit) {
            return response()->json([
                'code'    => 'MONTHLY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your monthly payment limit of Tk{$monthlyLimit}.",
            ], 400);
        }

        // check payment max limit
        if ($request->amount > $user->transactionLimit->payment_max) {
            return response()->json([
                'code'    => 'PAYMENT_MAX_LIMIT',
                'message' => "You cannot payment more than Tk{$user->transactionLimit->payment_max}.",
            ], 400);
        }

        // check balance
        if ($user->wallet->balance < $request->amount) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance',
            ], 302);
        }

        $toUser = User::where('phone', $validated['user_phone'])->first();

        // check same user
        if ($user->id == $toUser->id) {
            return response()->json([
                'code'    => 'SAME_USER',
                'message' => 'Couldn\'t process the transaction against same user',
            ], 400);
        }

        // check same amount transfer under 2 minutes
        $lastTransaction = Transaction::where('from_user_id', $user->id)
            ->where('to_user_id', $toUser->id)
            ->where('amount', $validated['amount'])
            ->where('created_at', '>=', Carbon::now()->subMinutes(2))
            ->first();

        if ($lastTransaction) {
            return response()->json([
                'code'    => 'FREQUENT_TRANSACTION',
                'message' => 'You cannot payment the same amount to the same agent within 2 minutes.',
            ], 400);
        }

        // check user role
        if ($toUser->role !== 'merchant') {
            return response()->json([
                'code'    => 'ROLE_RESTRICTED',
                'message' => "You couldn't payment to any {$toUser->role}.",
            ], 400);
        }

        try {
            DB::beginTransaction();

            // added money in merchant wallet
            $merchant->wallet->balance += ($validated['amount']);
            $merchant->wallet->save();

            // sub-struct money from user wallet
            $user->wallet->balance -= $request->amount;
            $user->wallet->save();

            // Log transaction, track agent
            $transaction = Transaction::create([
                'from_user_id' => $user->id,
                'to_user_id'   => $merchant->id,
                'amount'       => $validated['amount'],
                'type'         => 'payment',
                'status'       => 'completed',
                'txn_id'       => uniqid(),
                'reference'   => $request->reference ?? '',
                'charge_amount' => $chargeAmount,
            ]);
            DB::commit();

            $date = Carbon::parse($transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');

            // masking phone numbers for privacy
            $userMask = maskPhone($toUser->phone);
            $agentMask = maskPhone($merchant->phone);

            $ref = $request->reference ?? '';

            // make number formatting
            $amount = number_format($validated['amount'], 2);
            $chargeAmount = number_format($chargeAmount, 2);


            // user message
            $this->smsInit("You have paid Tk{$amount} to {$agentMask} Fee Tk{$chargeAmount} Ref:{$ref} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$user->wallet->balance}", "Paid {$amount} ", $user->phone, null, $user->name);

            // merchant message
            $this->smsInit("Payment received Tk{$amount} from {$userMask} successful Ref:{$ref} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$merchant->wallet->balance}", "Received payment {$amount} ", $merchant->phone, null, $merchant->name);

            return response()->json([
                'code'    => 'PAYMENT',
                'message' => 'Payment successful',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 500);
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

        $merchantPayment = Transaction::where('to_user_id', $user->id)
            ->where('type', 'transfer')
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
