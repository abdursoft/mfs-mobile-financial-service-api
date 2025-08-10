<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    /**
     * Upload KYC documents.
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type'         => 'required|in:nid,passport,driver_license',
            'document_number'       => 'required|string|max:50',
            'user_image'            => 'required|image|max:2048',
            'document_front_image'  => 'required|image|max:2048',
            'document_back_image'   => 'required|image|max:2048',
        ]);

        $user = authUser($request);

        if(!empty($user->kyc) && ($user->kyc->status === 'pending' || $user->kyc->status === 'approved')){
            return response()->json([
                'code' => 'KYC_PENDING',
                'message' => 'Your KYC in our review queue, we will update you within 24 hours'
            ]);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Store images
        $userImagePath = $request->file('user_image')->store('kyc/user_images', 'public');
        $frontImagePath = $request->file('document_front_image')->store('kyc/front', 'public');
        $backImagePath = $request->file('document_back_image')->store('kyc/back', 'public');

        // Create or update KYC
        $kyc = Kyc::updateOrCreate(
            ['user_id' => authUser($request)->id],
            [
                'user_image'            => $userImagePath,
                'document_number'       => $request->document_number,
                'document_front_image'  => $frontImagePath,
                'document_back_image'   => $backImagePath,
                'document_type'         => $request->document_type,
                'status'                => 'pending',
                'host_url'              => $request->root()."/storage"
            ]
        );

        return response()->json([
            'message' => 'KYC documents uploaded successfully. Pending admin approval.',
            'kyc'     => $kyc,
        ]);
    }

    /**
     * View KYC status.
     */
    public function status(Request $request)
    {
        $kyc = authUser($request)->kyc;

        if (! $kyc) {
            return response()->json([
                'code'    => 'INVALID_KYC',
                'message' => 'Please update your KYC'
            ], 401);
        }

        return response()->json($kyc);
    }
}

