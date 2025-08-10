<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Essentials\JWTAuth;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Wallet;
use App\Traits\MessageHandler;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use MessageHandler;
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "name"  => "required|string",
            "phone" => "required|string|unique:users,phone",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'User registration failed',
                'errors'  => $validator->errors(),
            ], 400);
        }

        $exists = User::where('phone', $request->input('phone'))->first();
        if ($exists && ! $exists->phone_verified_at) {
            $token       = rand(1000, 9999);
            $exists->otp = $token;
            $exists->save();

            $text = "ABPay signup OTP: $token will expire in 3 minutes. Please don't share your OTP and PIN with anyone";
            $this->smsInit($text, 'Sign up OTP', $request->phone, null, $request->input('name'));
            $token       = JWTAuth::createToken('otpToken', 0.05, null, $request->input('phone'));
            $resendToken = JWTAuth::createToken('resendToken', 1, null, $request->input('phone'));

            return response()->json([
                'code'      => 'VERIFICATION_CODE_SENT',
                'message'   => 'A verification code has been sent to your phone',
                'otpToken'  => $token,
                'resendOTP' => $resendToken,
            ], 201);
        } elseif ($exists && $exists->is_verified) {
            return response()->json([
                'code'    => 'PHONE_ALREADY_EXISTS',
                'message' => 'This phone already exist, Please login',
            ], 400);
        } else {
            true;
        }

        try {
            DB::beginTransaction();
            $token = rand(1000, 9999);
            if (! empty($request->role) && $request->role == 'admin') {
                $count = User::where('role', 'admin')->count();
                if ($count >= 1) {
                    return response()->json([
                        'code'    => 'ADMIN_EXISTS',
                        'message' => 'Admin registration over',
                    ], 400);
                }
            }
            User::create(
                [
                    "name"     => $request->input('name'),
                    "otp"      => $token,
                    "role"     => $request->role ?? 'user',
                    "phone"    => $request->phone,
                    "password" => Hash::make($request->input('password')),
                ]
            );
            $text = "ABPay signup OTP: $token will expire in 3 minutes. Please don't share your OTP and PIN with anyone";
            $this->smsInit($text, 'Sign up OTP', $request->phone, null, $request->input('name'));
            $token       = JWTAuth::createToken('otpToken', 0.05, null, $request->input('phone'));
            $resendToken = JWTAuth::createToken('resendToken', 1, null, $request->input('phone'));

            DB::commit();
            return response([
                'code'      => 'VERIFICATION_CODE_SENT',
                'message'   => 'A verification Code has been sent to your phone',
                'otpToken'  => $token,
                'resendOTP' => $resendToken,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'code'    => 'INTERNAL_SERVER_ERROR',
                'message' => 'User registration failed',
                'errors'  => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Profile image upload
     */
    public function profileImage(Request $request)
    {
        if ($request->hasFile('image')) {
            try {
                $user   = User::find($request->header('id'));
                $upload = Storage::disk('public')->put("uploads/profile", $request->file('image')) ?? $user->image;
                User::where('id', $request->header('id'))->update([
                    'image' => $request->root() . "/storage/" . $upload,
                    'path'  => $upload,
                ]);
                if (! empty($user->image)) {
                    Storage::disk('public')->delete($user->path);
                }
                return response()->json([
                    'code'    => 'PROFILE_IMAGE_UPDATED',
                    'message' => 'Profile image successfully updated',
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => 'Profile image couldn\'t update',
                    'errors'  => $th->getMessage(),
                ], 400);
            }
        } else {
            return response()->json([
                'code'    => 'IMAGE_REQUIRED',
                'message' => 'Please provide an image with a post request',
            ], 400);
        }
    }

    /**
     * Update user profile data
     */
    public function profileData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => "required|string|unique:users,name," . $request->user->id . ",id",
            'email' => "required|string|unique:users,email," . $request->user->id . ",id",
            'bio'   => "required|max:25",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 'INVALID_DATA',
                'message' => 'Profile data couldn\'t update',
                'errors'  => $validator->errors(),
            ], 400);
        }

        try {
            User::where('id', $request->user->id)->update($validator->validate());
            return response()->json([
                'code'    => 'PROFILE_DATA_UPDATED',
                'message' => 'Profile data successfully updated',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Internal server error',
            ], 400);
        }
    }

    /**
     * setup verification to the user
     */
    public function setupVerification($token)
    {
        try {
            $token = JWTAuth::verifyToken($token, false);
            $user  = User::where('email', $token->email)->first();
            if ($user->is_verified) {
                return redirect('/user/login')->with(true, "Account already verified");
            } else {
                User::where('email', $token->email)->update([
                    'is_verified' => 1,
                    'otp'         => null,
                ]);
                return redirect('/user/login')->with(true, "Account successfully verified");
            }
        } catch (Exception $e) {
            return redirect('/user/login')->with('error', "Account couldn\'t verified");
        }
    }

    /**
     * Verifying signup otp
     */
    public function verifySignupOTP(Request $request)
    {
        $token = JWTAuth::verifyToken($request->header('otpToken'), false);
        try {
            $user = User::where('phone', $token->phone)->first();
            if ($request->input() != '' && $user->otp !== 0 && $user->otp == $request->input('otp')) {
                User::where('id', $user->id)->update([
                    'otp'               => 0,
                    'otp_hit'           => 0,
                    'phone_verified_at' => now(),
                ]);
                $this->smsInit("{$user->name} your account has been verified, Please set your wallet PIN & update your KYC", 'Account Verified', $user->phone, $user->email, $user->name);
                $token = JWTAuth::createToken('walletPin', 8470, null, $user->phone);
                return response()->json(
                    [
                        'code'        => 'ACCOUNT_VERIFIED',
                        'message'     => "Account successfully verified",
                        'walletToken' => $token,
                    ],
                    200
                );
            } else {
                return response()->json(
                    [
                        'code'    => 'OTP_NOT_MATCH',
                        'message' => "OTP not matched",
                    ],
                    400
                );
            }
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'code'    => 'SERVER_ERROR',
                    'message' => "OTP verification session has been expired",
                ],
                401
            );
        }
    }

    /**
     * Verifying signup otp
     */
    public function resendOTP(Request $request)
    {
        try {
            $user = User::where('phone', $request->phone)->first();

            if (! $user) {
                return response()->json([
                    'code'    => 'USER_NOT_FOUND',
                    'message' => 'User not found',
                ], 404);
            }

            $otpExpired = Carbon::parse($user->updated_at)->addMinutes(3)->isPast();

            if ((int) $user->otp_hit < 3 && $otpExpired) {
                $token         = rand(1000, 9999);
                $user->otp     = $token;
                $user->otp_hit = $user->otp_hit + 1;
                $user->save();

                $text    = "ABPay signup OTP: $token will expire in 3 minutes. Please don't share your OTP and PIN with anyone";
                $jwToken = JWTAuth::createToken('otpToken', 0.05, null, $request->input('phone'));
                $this->smsInit($text, 'Sign up OTP', $user->phone, $user->email, $user->name);

                return response()->json([
                    'code'     => 'OTP_SENT',
                    'message'  => "We sent a new verification code",
                    'otpToken' => $jwToken,
                ], 200);
            } elseif (! (int) $user->otp_hit < 3) {
                return response()->json([
                    'code'    => 'OTP_LIMIT_END',
                    'message' => "You have tried your maximum limit, please try again later",
                ], 400);
            } else {
                return response()->json([
                    'code'    => 'OTP_COUNTDOWN',
                    'message' => "OTP was recently sent. Please wait 3 minutes before requesting again.",
                ], 429);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => 'SERVER_ERROR',
                'message' => "Authentication failed",
                "error"   => $th->getMessage() . ' ' . $th->getCode(),
            ], 401);
        }
    }

    /**
     * Login the user with jwt token
     */
    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'pin'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 'INVALID_DATA',
                'message' => 'Authentication failed',
                'errors'  => $validator->errors(),
            ], 401);
        }

        // Determine if the user input is an email or phone number
        $user = User::where('phone', $request->phone)->select('id', 'name', 'image', 'phone', 'role', 'phone_verified_at', 'pin' )->first();

        if ($user) {
            if (Hash::check($request->input('pin'), $user->pin)) {
                $token = JWTAuth::createToken($user->role, 1, $user->id, $user->phone);
                $this->authenticated($request, $user, $token);

                $refToken = JWTAuth::paymentToken('refToken', 8475, $user->id,null);

                $expire = now()->addSeconds(3600);

                $user->api_token        = $token;
                $user->token_expired_at = $expire;
                $user->save();

                return response()->json([
                    'code'       => 'LOGIN_SUCCESS',
                    'message'    => 'Login successful',
                    'role'       => $user->role,
                    'token'      => $token,
                    'refToken'   => $refToken,
                    'expires_in' => $expire,
                    'token_type' => 'Bearer',
                ], 200);
            } else {
                return response()->json([
                    'code'    => 'INCORRECT_PIN',
                    'message' => 'Incorrect PIN',
                ], 400);
            }
        } else {
            return response()->json([
                'code'    => 'LOGIN_FAILED',
                'message' => 'User not found',
            ], 404);
        }
    }

    // user signout action
    public function signout(Request $request)
    {
        try {
            $user = $request->attributes->get('auth_user');

            if ($user) {
                $user->api_token        = null;
                $user->token_expired_at = null;
                $user->save();
            }

            UserSession::where('user_id', $user->id)
                ->where("session_id", $request->bearerToken())->delete();

            return response()->json([
                'code'    => 'SIGNOUT_SUCCESSFUL',
                'message' => 'Signout action successful',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'code'    => 'SIGNOUT_FAILED',
                'message' => 'Signout action failed!',
            ], 400);
        }
    }

    // check authenticated user
    public function checkAuthUser(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if ($user) {
            return response()->json([
                'code'    => 'USER_AUTHENTICATED',
                'message' => 'User is authenticated',
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'phone' => $user->phone,
                    'role'  => $user->role,
                    'image' => $user->image,
                    'balance' => $user->wallet ? $user->wallet->balance : 0,
                    'kyc_status' => $user->kyc ? $user->kyc->status : 'not_verified',
                    'document' => $user->kyc ? maskPhone($user->kyc->document_number) : null,
                    'document_type' => $user->kyc ? $user->kyc->document_type : null,
                ],
            ], 200);
        } else {
            return response()->json([
                'code'    => 'UNAUTHORIZED',
                'message' => 'User is not authenticated',
            ], 401);
        }
    }

    /**
     * Refresh JWT token.
     */
    public function refresh(Request $request)
    {
        $user = $request->header('refreshToken');
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $decodedToken = JWTAuth::decodeToken($user, false);
        if (! $decodedToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        $user = User::find($decodedToken->id);
        $token = JWTAuth::createToken($user->role, 1, $user->id, $user->phone);

        $user->api_token        = $token;
        $user->token_expired_at = now()->addSeconds(3600);
        $user->save();

        return response()->json([
            'token' => $token,
            'expires_in'   => now()->addSeconds(3600),
        ]);
    }

    // user login limit authenticated
    protected function authenticated(Request $request, $user, $token)
    {

        // Save this session
        UserSession::create([
            'user_id'    => $user->id,
            'session_id' => $token,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        // Get all sessions (excluding current)
        $userSessions = UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $token)
            ->orderBy('created_at')
            ->get();

        if ($userSessions->count() >= 1) {
            $sessionsToRemove = $userSessions->take($userSessions->count());

            foreach ($sessionsToRemove as $session) {
                $session->delete();
            }
        }
    }

    /**
     * Set transaction PIN.
     */
    public function setPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $token = JWTAuth::verifyToken($request->header('walletToken'), false);
        if (! $token) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Wallet token is missing in your request header',
            ], 401);
        }

        $user      = User::where('phone', $token->phone)->first();
        $user->pin = Hash::make($request->pin);
        $user->save();

        // Create wallet for user (if not exist)
        Wallet::firstOrCreate(['user_id' => $user->id], [
            'balance' => 0,
        ]);

        return response()->json(['message' => 'PIN set successfully. Wallet created.']);
    }
}
