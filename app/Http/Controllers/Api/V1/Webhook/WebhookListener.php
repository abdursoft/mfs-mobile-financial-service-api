<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookListener extends Controller
{
    /**
     * List webhook groups with children
     */
    public function index()
    {
        return WebhookEvent::where('type', 'parent')
            ->with('children')
            ->orderBy('order')
            ->get();
    }

    /**
     * Store a webhook group with children
     */
    public function store(Request $request)
    {
        $request->validate([
            'webhook_id' => 'required|string',
            'hook_name'  => 'required|string',
            'children'   => 'array',
            'children.*' => 'string',
        ]);

        return DB::transaction(function () use ($request) {

            $order = WebhookEvent::max('order') + 1;

            // Parent
            $parent = WebhookEvent::create([
                'webhook_id' => $request->webhook_id,
                'hook_name'  => $request->hook_name,
                'type'       => 'parent',
                'parent_id'  => 0,
                'order'      => $order,
            ]);

            // Children
            foreach ($request->children ?? [] as $child) {
                WebhookEvent::create([
                    'webhook_id' => $request->webhook_id,
                    'hook_name'  => $child,
                    'type'       => 'child',
                    'parent_id'  => $parent->id,
                    'order'      => ++$order,
                ]);
            }

            return response()->json([
                'message' => 'Webhook event created successfully',
                'data'    => $parent->load('children'),
            ], 201);
        });
    }

    /**
     * Show single webhook group
     */
    public function show($id)
    {
        return WebhookEvent::with('children')
            ->where('id', $id)
            ->where('type', 'parent')
            ->firstOrFail();
    }

    /**
     * Update parent & children
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'hook_name'  => 'required|string',
            'children'   => 'array',
            'children.*' => 'string',
        ]);

        return DB::transaction(function () use ($request, $id) {

            $parent = WebhookEvent::where('id', $id)
                ->where('type', 'parent')
                ->firstOrFail();

            $parent->update([
                'hook_name' => $request->hook_name,
            ]);

            // Remove old children
            WebhookEvent::where('parent_id', $parent->id)->delete();

            // Recreate children
            $order = $parent->order;
            foreach ($request->children ?? [] as $child) {
                WebhookEvent::create([
                    'webhook_id' => $parent->webhook_id,
                    'hook_name'  => $child,
                    'type'       => 'child',
                    'parent_id'  => $parent->id,
                    'order'      => ++$order,
                ]);
            }

            return response()->json([
                'message' => 'Webhook event updated successfully',
                'data'    => $parent->load('children'),
            ]);
        });
    }

    /**
     * Delete parent & children
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {

            $parent = WebhookEvent::where('id', $id)
                ->where('type', 'parent')
                ->firstOrFail();

            WebhookEvent::where('parent_id', $parent->id)->delete();
            $parent->delete();

            return response()->json([
                'message' => 'Webhook event deleted successfully',
            ]);
        });
    }
}
