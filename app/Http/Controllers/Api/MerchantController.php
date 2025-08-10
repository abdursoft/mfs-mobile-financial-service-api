<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MerchantController extends Controller
{
    /**
     * Receive payment from customer.
     */
    public function receivePayment(Request $request)
    {
        $validated = $request->validate([
            'customer_phone' => 'required|exists:users,phone',
            'amount'         => 'required|numeric|min:1',
            'pin'            => 'required|digits:4',
        ]);

        $customer = User::where('phone', $validated['customer_phone'])->first();

        // check pin
        if (! Hash::check($validated['pin'], $customer->pin)) {
            return response()->json(['message' => 'Invalid customer PIN'], 401);
        }

        // check balance 
        if ($customer->wallet->balance < $validated['amount']) {
            return response()->json(['message' => 'Customer has insufficient balance'], 400);
        }

        $merchant = $request->user();

        DB::transaction(function () use ($customer, $merchant, $validated) {
            // Debit customer
            $customer->wallet->balance -= $validated['amount'];
            $customer->wallet->save();

            // Credit merchant
            $merchant->wallet->balance += $validated['amount'];
            $merchant->wallet->save();

            // Log transaction
            Transaction::create([
                'from_user_id' => $customer->id,
                'to_user_id'   => $merchant->id,
                'amount'       => $validated['amount'],
                'type'         => 'payment',
                'status'       => 'completed',
            ]);
        });

        return response()->json(['message' => 'Payment received successfully']);
    }

    public function createPaymentRequest(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $merchant = $request->user();

        $paymentRequest = PaymentRequest::create([
            'merchant_id' => $merchant->id,
            'reference'   => strtoupper(Str::random(10)),
            'amount'      => $validated['amount'],
            'status'      => 'pending',
            'expires_at'  => Carbon::now()->addMinutes(10),
        ]);

        return response()->json([
            'message'   => 'Payment request created',
            'reference' => $paymentRequest->reference,
        ]);
    }

    public function getPaymentQr(Request $request, $reference)
    {
        $merchant = $request->auth()->user();

        $paymentRequest = $merchant->paymentRequests()
            ->where('reference', $reference)
            ->firstOrFail();

        // Generate data: you can keep it simple
        $qrData = json_encode([
            'reference' => $paymentRequest->reference,
            'amount'    => $paymentRequest->amount,
        ]);

        // Return as PNG base64
        $qrCode = base64_encode(QrCode::format('png')->size(300)->generate($qrData));

        return response()->json(['qr_code' => $qrCode]);
    }

    public function listPaymentRequests(Request $request)
    {
        $merchant = $request->user();
        $requests = PaymentRequest::where('merchant_id', $merchant->id)
            ->orderByDesc('id')->get();

        return response()->json($requests);
    }



    /**
     * Merchant dashboard: total payments received
     */
    public function dashboard(Request $request)
    {
        $totalReceived = Transaction::where('to_user_id', authUser($request)->id)
            ->where('type', 'payment')
            ->sum('amount');

        return response()->json(['total_received' => $totalReceived]);
    }
}
