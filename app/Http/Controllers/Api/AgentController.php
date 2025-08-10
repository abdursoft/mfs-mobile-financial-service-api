<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Traits\MessageHandler;
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
            'pin'        => 'required|digits:4'
        ]);

        $user = User::where('phone', $validated['user_phone'])->first();
        $agent = $request->attributes->get('auth_user');

        // check pin
        if(!Hash::check($request->pin,$agent->pin)){
            return response()->json([
                'code' => 'INVALID_PIN',
                'message' => 'Incorrect wallet pin'
            ],401);
        }

        // check balance
        if($agent->wallet->balance < $request->amount){
            return response()->json([
                'code' => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance'
            ],302);
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
                'from_user_id' => $agent->id,   // agent performed cash-in
                'to_user_id'   => $user->id,
                'amount'       => $validated['amount'],
                'type'         => 'cash_in',
                'status'       => 'completed',
                'txn_id'       => uniqid()
            ]);
            DB::commit();
            // user message
            $this->smsInit("You have successfully cashed in Tk{$request->amount} from {$agent->phone}. TxID:{$transaction->txn_id} Your new balance is Tk {$user->wallet->balance}","Cash-in {$request->amount}", $user->phone, null, $user->name);

            // agent message
            $this->smsInit("Cashed in charge in Tk{$request->amount} to {$user->phone} TxID:{$transaction->txn_id} Your new balance is Tk {$agent->wallet->balance}","Cash-in {$request->amount}", $agent->phone, null, $agent->name);

            return response()->json([
                'code' => 'CASH_IN',
                'message' => 'Cash-in successful'
            ],200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Agent dashboard: total cash-in / cash-out done
     */
    public function dashboard(Request $request)
    {
        $cashInTotal = Transaction::where('type', 'cash_in')->sum('amount');
        $cashOutTotal = Transaction::where('type', 'cash_out')->sum('amount');

        return response()->json([
            'cash_in_total'  => $cashInTotal,
            'cash_out_total' => $cashOutTotal,
        ]);
    }
}
