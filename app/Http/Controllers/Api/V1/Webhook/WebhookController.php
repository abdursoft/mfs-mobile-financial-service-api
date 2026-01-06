<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\MerchantCredential;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    protected $merchant;
    protected $user;

    /**
     * Initialize the merchant app
     */
    public function __construct()
    {
        $this->merchant = merchantApp(request());
        $this->user = authUser(request());
    }

    /**
     * Display all webhooks for the authenticated merchant.
     */
    public function index()
    {
        $webhooks = Webhook::where('merchant_app_id', $this->merchant->id)
            ->latest()
            ->get();

        return response()->json([
            'code' => 'WEBHOOK_LIST_RETRIEVED',
            'message' => 'Webhooks retrieved successfully.',
            'data' => $webhooks,
        ]);
    }

    /**
     * Store a new webhook.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'webhook_url' => 'required|url',
            'webhook_events' => 'nullable|array',
            'webhook_type' => 'required|in:production,development',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'code' => 'INVALID_DATA',
                'message' => 'Invalid webhook data. Please check your request and try again.',
                'errors' => $validated->errors(),
            ], 422);
        }

        $form = $validated->validated();
        $form['merchant_app_id'] = $this->merchant->id;
        $form['webhook'] = uniqueToken(Webhook::class, 'webhook', 'wehk_', 32);
        $form['webhook_key'] = uniqueToken(Webhook::class, 'webhook_key', 'webhk_', 32);
        $form['webhook_sec'] = uniqueToken(Webhook::class, 'webhook_sec', 'websk_', 32);

        $webhook = Webhook::create($form);

        return response()->json([
            'code' => 'WEBHOOK_CREATED',
            'message' => 'Webhook created successfully.',
            'data' => $webhook,
        ], 201);
    }

    /**
     * Display a single webhook.
     */
    public function show($id=null)
    {
        if (!$id) {
            $webhook = Webhook::where('merchant_app_id', $this->merchant->id)->get();
        }else{
            $webhook = Webhook::where('merchant_app_id', $this->merchant->id)
            ->where('webhook', $id)
            ->first();
        }

        return response()->json([
            'code' => 'WEBHOOK_RETRIEVED',
            'message' => 'Webhook retrieved successfully.',
            'data' => $webhook,
        ]);
    }

    /**
     * Update an existing webhook.
     */
    public function update(Request $request, $id)
    {
        $webhook = Webhook::where('merchant_app_id', $this->merchant->id)
            ->where('webhook', $id)
            ->first();

        if (!$webhook) {
            return response()->json([
                'code' => 'WEBHOOK_NOT_FOUND',
                'message' => 'Webhook ID or authentication is invalid!',
            ], 404);
        }

        $validated = Validator::make($request->all(), [
            'webhook_url' => 'nullable|url',
            'webhook_events' => 'nullable|array',
            'webhook_type' => 'nullable|in:production,development',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'code' => 'INVALID_DATA',
                'message' => 'Invalid webhook data. Please check your request and try again.',
                'errors' => $validated->errors(),
            ], 422);
        }

        $form = $validated->validated();

        $webhook->update($form);

        return response()->json([
            'code' => 'WEBHOOK_UPDATED',
            'message' => 'Webhook updated successfully.',
            'data' => $webhook,
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroy($id)
    {
        $webhook = Webhook::where('merchant_app_id', $this->merchant->id)
            ->where('webhook', $id)
            ->first();

        if (!$webhook) {
            return response()->json([
                'code' => 'WEBHOOK_NOT_FOUND',
                'message' => 'Webhook ID or authentication is invalid!',
            ], 404);
        }

        $webhook->delete();

        return response()->json([
            'code' => 'WEBHOOK_DELETED',
            'message' => 'Webhook deleted successfully.',
        ]);
    }

    /**
     * Get webhook by merchant app name
     */
    public function getWebhookByMerchantAppName($appName){
        $app = MerchantCredential::with('webhook')->where('app_name',$appName)->first();
        if($app->user_id == $this->user?->id){
            $webhook = $app->webhook;
            return response()->json([
                'code' => 'WEBHOOK_RETRIEVED',
                'message' => 'Webhook retrieved successfully.',
                'webhook' => $webhook,
            ]);
        }

        return response()->json([
            'code' => 'WEBHOOK_NOT_FOUND',
            'message' => 'Webhook is not associated with '.$appName,
        ],422);
    }
}
