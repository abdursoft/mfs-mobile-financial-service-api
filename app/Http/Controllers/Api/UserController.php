<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Essentials\JWTAuth;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\MessageHandler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // use sms handler
    use MessageHandler;

    // user dashboard
    public function dashboard(Request $request)
    {
        $user = authUser($request) ?? User::find(3);

        $transactions = Transaction::where('to_user_id', $user->id)
            ->orWhere('from_user_id', $user->id)
            ->with([
                'fromUser' => function ($from) {
                    $from->select('id', 'name', 'phone', 'image', 'role');
                },
                'toUser'   => function ($to) {
                    $to->select('id', 'name', 'phone', 'image', 'role');
                }])
            ->latest()
            ->limit(20)
            ->get();

        $count = Transaction::where('to_user_id', $user->id)
            ->orWhere('from_user_id', $user->id)->count();

        $total = Transaction::where('to_user_id', $user->id)
            ->orWhere('from_user_id', $user->id)->sum('amount');

        return response()->json([
            'name'                      => $user->name,
            'phone'                     => $user->phone,
            'role'                      => $user->role,
            'image'                     => $user->image,
            'kyc_status'                => $user->kyc->status,
            'balance'                   => $user->wallet->balance,
            'total_transactions_count'  => $count,
            'total_transactions_amount' => $total,
            'transactions'              => $transactions,
        ]);
    }

    // reset pin
    public function pinReset(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'phone' => 'string|required|exists:users,phone',
        ]);

        // check phone validation
        if ($validate->fails()) {
            return response()->json([
                'code'    => 'INVALID_DATA',
                'message' => 'Please enter your valid phone number',
                'errors'  => $validate->errors(),
            ], 422);
        }

        // get user according the phone
        $user = User::where('phone', $request->phone)->first();

        // update user table
        $code          = otp();
        $user->otp     = $code;
        $user->otp_hit = 0;
        $user->save();

        // send otp in user phone
        $this->smsInit("Your PIN reset OTP {$code}. Please don't share your OTP and PIN with anyone", 'PIN reset', $user->phone, null, $user->name);

        // generate reset token
        $token = JWTAuth::paymentToken('resetToken', 0.17, $user->id);

        return response()->json([
            'code'       => 'RESET_OTP_SENT',
            'message'    => 'We have sent a OTP in your phone',
            'token_type' => 'Bearer',
            'resetToken' => $token,
            'expire_in'  => 'Token will expire in 10 minutes',
        ], 200);
    }

    // resend pin reset otp
    public function resendPinResetOTP(Request $request)
    {
        // check header resetToken
        $token = $request->header('resetToken');
        if (! $token) {
            return response()->json([
                'code'    => 'REQUIRED_HEADER_IS_MISSING',
                'message' => 'Please send the resetToken in your request header',
            ], 401);
        }
        $decode_token = JWTAuth::decodeToken($token, false);

        // check user
        $user = User::find($decode_token->id);

        if (! $user) {
            return response()->json([
                'code'    => 'INVALID_USER',
                'message' => 'Couldn\'t find the user',
            ],404);
        }

        // check min time
        if ($user->updated_at > Carbon::now()->subMinutes(3)) {
            return response()->json([
                'code'    => 'RESEND_TIMER',
                'message' => 'Please try again 3 minutes',
            ],400);
        }

        // update user table
        $code          = otp();
        $user->otp     = $code;
        $user->otp_hit = 0;
        $user->save();

        // send otp in user phone
        $this->smsInit("Your PIN reset OTP {$code}. Please don't share your OTP and PIN with anyone", 'PIN reset', $user->phone, null, $user->name);

        // generate reset token
        $token = JWTAuth::paymentToken('resetToken', 0.17, $user->id);

        return response()->json([
            'code'       => 'RESET_OTP_SENT',
            'message'    => 'We have sent a OTP in your phone',
            'token_type' => 'Bearer',
            'resetToken' => $token,
            'expire_in'  => 'Token will expire in 10 minutes',
        ], 200);

    }

    // check pin otp
    public function checkPinOTP(Request $request)
    {
        // check header resetToken
        $token = $request->header('resetToken');
        if (! $token) {
            return response()->json([
                'code'    => 'REQUIRED_HEADER_IS_MISSING',
                'message' => 'Please send the resetToken in your request header',
            ], 401);
        }
        $decode_token = JWTAuth::decodeToken($token, false);

        // check OTP
        if (! $request->otp) {
            return response()->json([
                'code'    => 'INVALID_OTP',
                'message' => 'Please enter the 4 digits OTP from your phone',
            ], 422);
        }

        // check user
        $user = User::find($decode_token->id);

        if (! $user) {
            return response()->json([
                'code'    => 'INVALID_USER',
                'message' => 'Couldn\'t find the user',
            ],404);
        }

        // check user otp
        if ($request->otp !== $user->otp) {
            // update user otp hit
            $user->otp_hit += 1;
            $user->save();

            return response()->json([
                'code'    => 'INCORRECT_OTP',
                'message' => 'Please enter the correct OTP',
            ], 422);
        }

        // update user otp
        $user->otp = null;
        $user->save();

        // generate pin token
        $pin = JWTAuth::paymentToken('pinToken', 0.17, $user->id);

        return response()->json([
            'code'       => 'OTP_VERIFIED',
            'message'    => 'OTP successfully verified',
            'token_type' => 'Bearer',
            'pinToken'   => $pin,
            'expire_in'  => "Token will expire in 10 minutes",
        ], 200);
    }

    // set new pin
    public function newPin(Request $request)
    {
        // check header resetToken
        $token = $request->header('pinToken');
        if (! $token) {
            return response()->json([
                'code'    => 'REQUIRED_HEADER_IS_MISSING',
                'message' => 'Please send the pinToken in your request header',
            ], 401);
        }
        $decode_token = JWTAuth::decodeToken($token, false);

        // check input pin
        $validate = Validator::make($request->all(), [
            'new_pin'     => 'required|digits:4',
            'confirm_pin' => 'required|digits:4',
        ]);

        // input validation
        if ($validate->fails()) {
            return response()->json([
                'code'    => 'INVALID_DATA',
                'message' => 'Invalid request data',
                'errors'  => $validate->errors(),
            ], 422);
        }

        // check matching pin
        if ($request->new_pin !== $request->confirm_pin) {
            return response()->json([
                'code'    => 'REQUEST_PIN_NOT_MATCH',
                'message' => 'New pin and Confirm pin must be same',
            ], 422);
        }

        // check user
        $user = User::find($decode_token->id);

        if (! $user) {
            return response()->json([
                'code'    => 'INVALID_USER',
                'message' => 'Couldn\'t find the user',
            ], 404);
        }

        // update user table
        $user->pin = Hash::make($request->new_pin);
        $user->save();

        // send user confirmation message
        $this->smsInit("Your pin has been successfully reset",'PIN reset',$user->phone,null,$user->name);

        return response()->json([
            'code'    => 'PIN_RESET_SUCCESSFUL',
            'message' => 'Your pin has been successfully reset',
        ], 200);
    }
}
