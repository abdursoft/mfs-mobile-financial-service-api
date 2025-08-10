<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MerchantCredentialController extends Controller
{
    /**
     * List all merchant credentials (for the authenticated merchant)
     */
    public function index(Request $request)
    {
        $user        = authUser($request);
        $credentials = MerchantCredential::where('user_id', $user->id)->get();
        return response()->json($credentials);
    }

    /**
     * Store new merchant credential
     */
    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'app_name' => 'required|string|unique:merchant_credentials,app_name',
            'app_logo' => 'required|string',
            'app_type' => 'required|in:production,development',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'code'    => 'INVALID_DATA',
                'message' => 'Request data are invalid',
                'errors'  => $validate->errors(),
            ], 400);
        }

        $user = authUser($request);

        // check kyc
        if(!$user->kyc || $user->kyc->status !== 'approved'){
            return response()->json([
                'code'    => 'KYC_NOT_VERIFIED',
                'message' => 'Please update your KYC to create you app'
            ], 400);
        }

        try {


            $validated = $validate->validated();

            $validated['user_id']     = $user->id;
            $validated['secret_key']  = "sk" . generate_unique_token(\App\Models\MerchantCredential::class, 'secret_key', $request->app_type);
            $validated['public_key']  = "pk" . generate_unique_token(\App\Models\MerchantCredential::class, 'public_key', $request->app_type);
            $validated['webhook_key'] = "whk" . generate_unique_token(\App\Models\MerchantCredential::class, 'webhook_key', $request->app_type);

            $credential = MerchantCredential::create($validated);

            return response()->json([
                'code'    => 'MERCHANT_APP_CREATED',
                'message' => "Merchant app ($request->app_name) created successful.",
                'data'    => $credential,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Show single credential
     */
    public function show($id, Request $request)
    {
        $user       = authUser($request);
        $credential = MerchantCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json($credential);
    }

    /**
     * Show single credential
     */
    public function merchantShow(Request $request, $id = null)
    {
        $user = authUser($request);
        if ($id) {
            $credential = MerchantCredential::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $credential = MerchantCredential::where('user_id', $user->id)
                ->get();
        }

        return response()->json([
                'code'    => 'MERCHANT_APP_RETRIEVED',
                'message' => "Merchant app retrieved successful.",
                'data'    => $credential,
            ], 201);
    }

    /**
     * Update credential
     */
    public function update($id, Request $request)
    {
        $user       = authUser($request);
        $credential = MerchantCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'secret_key'     => 'sometimes|required|string',
            'public_key'     => 'sometimes|required|string',
            'webhook_key'    => 'sometimes|required|string',
            'webhook_url'    => 'sometimes|required|url',
            'webhook_events' => 'sometimes|required|array',
            'app_name'       => 'sometimes|required|string',
            'app_logo'       => 'sometimes|required|string',
            'app_type'       => 'sometimes|required|in:production,development',
            'status'         => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $credential->update($validated);

        return response()->json($credential);
    }

    /**
     * Delete credential
     */
    public function destroy($id, Request $request)
    {
        $user       = authUser($request);
        $credential = MerchantCredential::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $credential->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
