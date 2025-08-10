<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user (send OTP).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|unique:users,phone',
            'name'  => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create user without PIN yet
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'pin' => '', // empty for now
        ]);

        // Send OTP here (mock: 1234)
        // You can implement real SMS later
        // e.g., Notification::route('nexmo', $request->phone)->notify(new SendOtpNotification());

        return response()->json([
            'message' => 'Registered successfully. Use OTP 1234 to verify.',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Verify phone number with OTP.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'otp'     => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check OTP (mock: always 1234)
        if ($request->otp != '1234') {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        $user = User::find($request->user_id);
        $user->phone_verified_at = now();
        $user->save();

        return response()->json(['message' => 'Phone verified successfully']);
    }

    /**
     * Set transaction PIN.
     */
    public function setPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'pin'     => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);
        $user->pin = Hash::make($request->pin);
        $user->save();

        // Create wallet for user (if not exist)
        Wallet::firstOrCreate(['user_id' => $user->id], [
            'balance' => 0,
        ]);

        return response()->json(['message' => 'PIN set successfully. Wallet created.']);
    }

    /**
     * Login user (with phone & PIN).
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|exists:users,phone',
            'pin'   => 'required|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->pin, $user->pin)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
