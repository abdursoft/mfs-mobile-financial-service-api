<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Essentials\JWTAuth;
use App\Models\MerchantCredential;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    // message traits
    use MessageHandler;

    // payment init token
    public function createToken(Request $request)
    {
        // get and set header
        $app_name   = $request->header('app_name');
        $public_key = $request->header('public_key');

        // validate headers
        if (empty($app_name) || empty($public_key)) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'App name and public key is required',
            ], 422);
        }

        try {
            $merchant = MerchantCredential::where('public_key', $public_key)
                ->where('app_name', $app_name)->first();

            $token = JWTAuth::paymentToken('paymentToken', 0.17, $merchant->id);
            return response([
                'code'       => 'PAYMENT_TOKEN',
                'message'    => 'Payment token created successful',
                'token_type' => 'Bearer',
                'token'      => $token,
                'expire_in'  => '10 minutes',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid payment data, Please have a look our API docs.',
            ], 422);
        }
    }

    // create payment URL
    public function createPayment(Request $request)
    {
        // validate input data
        $validate = Validator::make($request->all(), [
            'reference' => 'nullable|string',
            'amount'    => 'required|numeric|min:1',
            'mr_txn_id' => 'required|string',
        ]);

        // check validation
        if ($validate->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid payment data, Please have a look our API docs.',
                'errors'  => $validate->errors(),
            ], 422);
        }

        try {
            $merchant = $request->attributes->get('merchantApp');

            // create transaction form data
            $form                    = $validate->validated();
            $form['txn_id']          = txnID(\App\Models\PaymentRequest::class, 'txn_id');
            $form['status']          = 'pending';
            $form['merchant_app_id'] = $merchant->id;
            $from['expire_at']       = Carbon::now()->addHour(24);

            $payment = PaymentRequest::create($form);
            return response()->json([
                'code'    => 'Payment created successful',
                'message' => 'Redirect to the below link to make payment done',
                'payment' => [
                    'payment_id'   => $payment->txn_id,
                    'merchant_id'  => $payment->mr_txn_id,
                    'status'       => $payment->status,
                    'amount'       => $payment->amount,
                    'currency'     => $payment->currency ?? 'BDT',
                    'reference'    => $payment->reference,
                    'created_at'   => $payment->created_at,
                    'payment_link' => ($merchant->app_type == 'development' ? env('DEMO_PAYMENT') : env('LIVE_PAYMENT')) . "?paymentID={$payment->txn_id}&mrID={$payment->mr_txn_id}",
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => "INVALID_REQUEST",
                'message' => 'Payment data couldn\'t process',
                'error '  => $th->getMessage(),
            ], 422);
        }
    }

    // get merchant by payment id
    public function merchantByPayment($id)
    {
        // check payment id
        $payment = PaymentRequest::findOrFail($id);

        // check is available
        if (empty($payment)) {
            return response()->json([
                'code'    => 'INVALID_PAYMENT_REQUEST_ID',
                'message' => 'Invalid payment request',
            ], 404);
        }

        // check payment is payable 
        if($payment->status !== 'pending'){
            return response()->json([
                'code'    => 'PAYMENT_IS_NOT_GRANTED',
                'message' => 'Payment request is not granted',
            ], 400);
        }

        $app = $payment->merchantApp;

        return response()->json([
            'code'     => 'MERCHANT_RETRIEVED',
            'message'  => 'Merchant retrieved successfully',
            'merchant' => [
                'app_name' => $app->app_name,
                'app_logo' => $app->app_logo,
                'amount'   => $payment->amount,
                'currency' => $payment->currency,
            ],
        ], 200);
    }

    // proceed payment
    public function proceedPayment($id, Request $request)
    {
        $validate = Validator::make($request->all(), [
            'phone' => 'required|exists:users,phone',
        ]);

        // check validation
        if ($validate->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid payment data, Please have a look our API docs.',
                'errors'  => $validate->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        // check user and role
        if (! $user || $user->role !== 'user') {
            return response()->json([
                'code'    => "WRONG_USER",
                'message' => 'You are not allowed to proceed this payment',
            ], 422);
        }

        // kyc check
        if (! $user->kyc || $user->kyc->status !== 'approved') {
            return response()->json([
                'code'    => 'KYC_NOT_VERIFIED',
                'message' => 'Please update your KYC to make your transaction',
            ], 400);
        }

        // check payment request
        $payment = PaymentRequest::where('txn_id', $id)->first();

        // check payment request status
        if ($payment->status !== 'pending') {
            return response()->json([
                'code'    => 'PAYMENT_DECLINED',
                'message' => 'Your payment has been declined',
            ], 422);
        }

        try {
            $merchant = $payment->merchant;

            $code = otp();

            // Log transaction, track agent
            $transaction = Transaction::create([
                'from_user_id' => $user->id, // agent performed cash-in
                'to_user_id'   => $merchant->id,
                'amount'       => $payment->amount,
                'payment_id'   => $payment->id,
                'type'         => 'payment',
                'status'       => 'pending',
                'otp'          => $code,
                'txn_id'       => uniqid(),
            ]);

            // send OTP
            $this->smsInit("Payment verification OTP {$code}. Don't share your PIN and OTP with anyone.", 'Payment OTP', $user->phone, null, $user->name);

            return response()->json([
                'code'    => 'Payment created successful',
                'message' => 'Your payment has been created. Please verify your phone',
                'data'    => $transaction,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => "INVALID_REQUEST",
                'message' => 'Payment data couldn\'t process',
                'error '  => $th->getMessage(),
            ], 422);
        }
    }

    // check payment OTP
    public function checkOTP(Request $request, $id)
    {
        // check input otp
        if (! $request->otp) {
            return response()->json([
                'code'    => "INVALID_OTP",
                'message' => 'Please enter 4 digits OTP from your phone.',
            ], 422);
        }

        $transaction = Transaction::where('txn_id', $id)->first();

        // check transaction
        if (! $transaction) {
            return response()->json([
                'code'    => "INVALID_TRANSACTION_ID",
                'message' => 'Please use a valid transaction ID.',
            ], 422);
        }

        // check transaction status
        if ($transaction && $transaction->status !== 'pending' && $transaction->type !== 'payment') {
            return response()->json([
                'code'    => "INVALID_TRANSACTION",
                'message' => 'Invalid transaction or server error',
            ], 422);
        }

        // check otp match
        if ($request->otp != $transaction->otp) {
            return response()->json([
                'code'    => "INVALID_OTP",
                'message' => 'Please enter your correct OTP',
            ], 422);
        }

        // update transaction  otp
        $transaction->otp = null;
        $transaction->save();

        return response()->json([
            'code'    => "OTP_VERIFIED",
            'message' => 'OTP has been verified',
        ], 200);
    }

    // check payment OTP
    public function checkPIN(Request $request, $id)
    {
        // check input otp
        if (! $request->pin) {
            return response()->json([
                'code'    => "INVALID_PIN",
                'message' => 'Please enter 4 digits wallet PIN.',
            ], 422);
        }

        $transaction = Transaction::where('txn_id', $id)->first();

        // check transaction
        if (! $transaction) {
            return response()->json([
                'code'    => "INVALID_TRANSACTION_ID",
                'message' => 'Please use a valid transaction ID.',
            ], 422);
        }

        // check transaction status
        if ($transaction && $transaction->status == 'completed') {
            return response()->json([
                'code'    => "INVALID_TRANSACTION",
                'message' => 'Invalid transaction or server error',
            ], 422);
        }

        // check otp
        if ($transaction->otp) {
            return response()->json([
                'code'    => "INVALID_OTP",
                'message' => 'Please verify your OTP first.',
            ], 422);
        }

        // define user and merchant
        $merchant = $transaction->toUser;
        $user     = $transaction->fromUser;
        $payment  = $transaction->payment;

        // check wallet pin
        if (! Hash::check($request->pin, $user->pin)) {
            return response()->json([
                'code'    => 'INVALID_WALLET_PIN',
                'message' => 'Please enter your correct PIN',
            ], 422);
        }

        // check balance
        if ($transaction->amount > $user->wallet->balance) {
            return response()->json([
                'code'    => 'INSUFFICIENT_BALANCE',
                'message' => 'You don\'t have sufficient balance to make this payment completed',
            ], 422);
        }

        try {
            // start database transaction
            DB::beginTransaction();

            // credit merchant wallet
            $merchant->wallet->balance += $transaction->amount;
            $merchant->wallet->save();

            // debit user wallet
            $user->wallet->balance -= $transaction->amount;
            $user->wallet->save();

            // change transaction status
            $transaction->status = 'completed';
            $transaction->save();

            // change payment request status
            $payment->status = 'paid';
            $payment->save();

            // committed database
            DB::commit();

            $date = Carbon::parse($transaction->created_at, 'UTC') // assume stored as UTC
                ->setTimezone('Asia/Dhaka')
                ->format('Y/m/d h:i:s A');

            // merchant confirmation sms
            $this->smsInit("You have received a payment Tk{$transaction->amount} from {$user->phone} on {$date} TxnID:{$transaction->txn_id}. You new balance is Tk{$merchant->wallet->balance}", "Received Payment Tk{$transaction->amount}", $merchant->phone, null, $merchant->name);

            // user confirmation sms
            $this->smsInit("Your payment has been completed to {$merchant->phone} Tk{$transaction->amount} on {$date} TxnID:{$transaction->txn_id}. You new balance is Tk{$user->wallet->balance}", "You have paid Tk{$transaction->amount}", $user->phone, null, $user->name);

            return response()->json([
                'code'    => "PAYMENT_COMPLETED",
                'message' => 'Thanks! Your payment has been completed',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code'    => "PAYMENT_FAILED",
                'message' => 'Your payment has been failed!',
                'errors'  => $th->getMessage(),
            ], 400);
        }
    }
}
