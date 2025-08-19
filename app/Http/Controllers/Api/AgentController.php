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

class AgentController extends Controller
{
    use MessageHandler;
    /**
     * Agent cash-in to user's wallet.
     */
    public function cashIn(Request $request)
    {
        $validated = $request->validate([
            'user_phone' => 'required|exists:users,phone',
            'amount'     => 'required|numeric|min:1',
            'pin'        => 'required|digits:4',
        ]);

        $user  = User::where('phone', $validated['user_phone'])->first();
        $agent = $request->attributes->get('auth_user');

        // check pin
        if (! Hash::check($request->pin, $agent->pin)) {
            return response()->json([
                'code'    => 'INVALID_PIN',
                'message' => 'Incorrect wallet pin',
            ], 401);
        }

        // check user kyc
        if (! $agent->kyc || $agent->kyc->status !== 'approved') {
            return response()->json([
                'code'    => 'KYC_NOT_VERIFIED',
                'message' => 'Please update your KYC to make your transaction',
            ], 400);
        }

        // check same user
        if ($agent->id == $user->id) {
            return response()->json([
                'code'    => 'SAME_USER',
                'message' => 'Couldn\'t process the transaction against same ID',
            ], 400);
        }

        // check user role
        if ($user->role !== 'user') {
            return response()->json([
                'code'    => 'ROLE_RESTRICTED',
                'message' => "You couldn't cash in to any {$user->role}.",
            ], 400);
        }

        // check same amount transfer under 2 minutes
        $lastTransaction = Transaction::where('from_user_id', $agent->id)
            ->where('to_user_id', $user->id)
            ->where('amount', $request->amount)
            ->where('created_at', '>=', Carbon::now()->subMinutes(2))
            ->first();

        if ($lastTransaction) {
            return response()->json([
                'code'    => 'FREQUENT_TRANSACTION',
                'message' => 'You cannot cash in the same amount to the same user within 2 minutes.',
            ], 400);
        }

        $charge = TransactionCharge::where('user_id', $user->id)->first();

        // check amount charge
        $amount       = $validated['amount'];
        $chargeAmount = ($amount * $charge->cash_in_percentage / 100);
        $request->merge(['amount' => $amount + $chargeAmount]);

        // check daily and monthly limits
        $dailyLimit   = $user->transactionLimit->daily_cash_in_limit;
        $monthlyLimit = $user->transactionLimit->monthly_cash_in_limit;
        $dailyTotal   = Transaction::where('to_user_id', $user->id)
            ->where('type', 'cash_in')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
        $monthlyTotal = Transaction::where('to_user_id', $user->id)
            ->where('type', 'cash_in')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('amount');

        if (($dailyTotal + $request->amount) > $dailyLimit) {
            return response()->json([
                'code'    => 'DAILY_LIMIT_EXCEEDED',
                'message' => "User have exceeded their daily cash in limit of Tk{$dailyLimit}.",
            ], 400);
        }

        if (($monthlyTotal + $request->amount) > $monthlyLimit) {
            return response()->json([
                'code'    => 'MONTHLY_LIMIT_EXCEEDED',
                'message' => "User have exceeded their cash in limit of Tk{$monthlyLimit}.",
            ], 400);
        }

        // check balance
        if ($agent->wallet->balance < $request->amount) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance',
            ], 302);
        }

        try {
            DB::beginTransaction();

            // added money in user wallet
            $user->wallet->balance += $validated['amount'];
            $user->wallet->save();

            // sub-struct money from agent wallet
            $agent->wallet->balance -= $validated['amount'];
            $agent->wallet->save();

            // Log transaction, track agent
            $transaction = Transaction::create([
                'from_user_id' => $agent->id, // agent performed cash-in
                'to_user_id'   => $user->id,
                'amount'       => $validated['amount'],
                'type'         => 'cash_in',
                'status'       => 'completed',
                'txn_id'       => uniqid(),
            ]);
            DB::commit();
            $date = Carbon::parse($transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');
            // user message
            $this->smsInit("You have successfully cashed in Tk{$request->amount} from {$agent->phone} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$user->wallet->balance}", "Cash-in {$request->amount}", $user->phone, null, $user->name);

            // agent message
            $this->smsInit("Cashed in charge Tk{$request->amount} to {$user->phone} on {$date} TxID:{$transaction->txn_id} Your new balance is Tk{$agent->wallet->balance}", "Cash-in {$request->amount}", $agent->phone, null, $agent->name);

            return response()->json([
                'code'    => 'CASH_IN',
                'message' => 'Cash-in successful',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => 'CASH_IN_FAIL',
                'message' => 'Cash-in couldn\'t successful',
            ], 400);
        }
    }

    /**
     * Agent dashboard: total cash-in / cash-out done
     */
    public function dashboard(Request $request)
    {
        $cashInTotal  = Transaction::where('type', 'cash_in')->sum('amount');
        $cashOutTotal = Transaction::where('type', 'cash_out')->sum('amount');

        return response()->json([
            'cash_in_total'  => $cashInTotal,
            'cash_out_total' => $cashOutTotal,
        ]);
    }
}
