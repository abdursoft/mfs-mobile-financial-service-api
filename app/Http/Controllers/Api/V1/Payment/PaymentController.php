<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Essentials\JWTAuth;
use App\Jobs\MerchantPaymentReceivedJob;
use App\Jobs\PaymentVerifyOTPJob;
use App\Jobs\UserPaymentCompleteJob;
use App\Models\MerchantCredential;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{

    // payment init token
    public function createToken(Request $request)
    {
        // get and set header
        $app_name   = $request->header('app_name');
        $app_mode = $request->header('app_mode');
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
                ->where('app_name', $app_name)->where('app_type',$app_mode)->first();

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
                'error'   => $th->getMessage()
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
            'cancel_url'=> 'required|url',
            'success_url' => 'required|url',
            'failed_url' => 'nullable|url',
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
        $payment = PaymentRequest::where('txn_id',$id)->first();

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

        $user = User::where('phone', format_phone($request->phone))->first();
        if(empty($user)){
            return response()->json([
                'code'    => "WRONG_USER",
                'message' => 'Invalid wallet number'
            ], 422);
        }

        // check user and role
        if ($user->role !== 'user') {
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

        // check payment id in transaction table
        if(!empty($payment->transaction)){
            return response()->json([
                'code'    => 'PAYMENT_DECLINED',
                'message' => 'Your payment has been declined for duplicate request ID',
            ], 422);
        }

        try {
            $merchant = $payment->merchantApp->user;

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
            dispatch(new PaymentVerifyOTPJob($user, $code))->onQueue('high');

            return response()->json([
                'code'    => 'Payment created successful',
                'message' => 'Your payment has been created. Please verify your phone',
                'data'    => $transaction,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => "INVALID_REQUEST",
                'message' => 'Payment data couldn\'t process',
            ], 422);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP($id, Request $request)
    {

        // check payment request
        $transaction = Transaction::where('txn_id', $id)->first();

        // check payment request status
        if ($transaction->status !== 'pending') {
            return response()->json([
                'code'    => 'PAYMENT_DECLINED',
                'message' => 'Your payment has been declined',
            ], 422);
        }

        // checking otp hit
        if($transaction->otp_hit >= 3){
            $transaction->status = 'canceled';
            $transaction->save();

            return response()->json([
                'code'    => 'PAYMENT_CANCELED',
                'message' => 'Your payment has been canceled',
                'callback_url' => $transaction->payment->cancel_url
            ], 200);
        }


        try {
            $user = $transaction->fromUser;

            $code = otp();

            // Log transaction, track agent
            $transaction->otp = $code;
            $transaction->otp_hit += 1;
            $transaction->save();

            // send OTP
            dispatch(new PaymentVerifyOTPJob($user, $code))->onQueue('high');

            return response()->json([
                'code'    => 'OTP sent successfully',
                'message' => 'A new OTP has been sent to your phone',
            ],200);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => "INVALID_REQUEST",
                'message' => 'Payment data couldn\'t process',
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
                'message' => 'Please enter 6 digits OTP from your phone.',
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


        $charge = TransactionCharge::where('user_id',$user->id)->first();

        // check amount charge
        $amount = $transaction->amount;
        $chargeAmount = ($amount * $charge->payment_percentage / 100);
        $amount += $chargeAmount;

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


        if (($dailyTotal + $amount) > $dailyLimit) {
            return response()->json([
                'code'    => 'DAILY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your daily payment limit of Tk{$dailyLimit}.",
            ], 400);
        }

        if (($monthlyTotal + $amount) > $monthlyLimit) {
            return response()->json([
                'code'    => 'MONTHLY_LIMIT_EXCEEDED',
                'message' => "You have exceeded your monthly payment limit of Tk{$monthlyLimit}.",
            ], 400);
        }

        // check cash-out max limit
        if ($request->amount > $user->transactionLimit->payment_max) {
            return response()->json([
                'code'    => 'PAYMENT_MAX_LIMIT',
                'message' => "You cannot pay more than Tk{$user->transactionLimit->payment_max}.",
            ], 400);
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
            $transaction->charge_amount = $chargeAmount;
            $transaction->save();

            // change payment request status
            $payment->status = 'paid';
            $payment->save();

            // committed database
            DB::commit();

            // merchant confirmation sms
            dispatch(new MerchantPaymentReceivedJob($user, $merchant, $transaction))->onQueue('medium');

            // user confirmation sms
            dispatch(new UserPaymentCompleteJob($user, $merchant, $transaction))->onQueue('medium');

            return response()->json([
                'code'    => "PAYMENT_COMPLETED",
                'message' => 'Thanks! Your payment has been completed',
                'callback_url' => $payment->success_url
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code'    => "PAYMENT_FAILED",
                'message' => 'Your payment has been failed!',
                'callback_url' => $payment->cancel_url
            ], 400);
        }
    }

    // get merchant payment
    public function getMerchantPayment(Request $request, $id){
        $payment = Transaction::where('txn_id',$id)->first();
        $merchant = $request->attributes->get('merchantApp');
        if($payment && $payment->toUser->id == $merchant->user_id){
            $data = $payment->toArray();
            unset($data['to_user']);
            return response()->json([
                'status' => 'PAYMENT_RETRIEVED',
                'message' => 'Payment successfully retrieved',
                'payment' => $data
            ],200);
        }

        return response()->json([
            'status' => 'PAYMENT_RETRIEVED_ERROR',
            'message' => 'Invalid payment id or request token'
        ],422);
    }
}

