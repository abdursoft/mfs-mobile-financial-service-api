<?php
namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent as ModelsWebhookEvent;

class WebhookEvent extends Controller
{
    /**
     * Show webhook events
     */
    public function show($id = null)
    {
        $events = $id == null ? ModelsWebhookEvent::with('children')->where('type','parent')->get() : ModelsWebhookEvent::find($id);
        return response()->json([
            'code'    => 'WEBHOOK_EVENT_RETRIEVED',
            'message' => "Webhook event retrieved successfully",
            'events'  => $events,
        ]);
    }
}
