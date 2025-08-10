<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    public function payPaymentRequest(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|exists:payment_requests,reference',
            'pin'       => 'required|digits:4'
        ]);

        $customer = $request->user();

        if (! Hash::check($validated['pin'], $customer->pin)) {
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        $paymentRequest = PaymentRequest::where('reference', $validated['reference'])->first();

        if ($paymentRequest->status !== 'pending') {
            return response()->json(['message' => 'Payment request already processed'], 400);
        }

        if ($customer->wallet->balance < $paymentRequest->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        DB::transaction(function() use ($customer, $paymentRequest) {
            // Debit customer
            $customer->wallet->balance -= $paymentRequest->amount;
            $customer->wallet->save();

            // Credit merchant
            $merchant = $paymentRequest->merchant;
            $merchant->wallet->balance += $paymentRequest->amount;
            $merchant->wallet->save();

            // Update payment request
            $paymentRequest->status = 'paid';
            $paymentRequest->save();

            // Log transaction
            Transaction::create([
                'from_user_id' => $customer->id,
                'to_user_id'   => $merchant->id,
                'amount'       => $paymentRequest->amount,
                'type'         => 'merchant_payment',
                'status'       => 'completed',
            ]);
        });

        return response()->json(['message' => 'Payment successful']);
    }
}
