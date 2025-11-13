<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Display all subscriptions.
     */
    public function index()
    {
        $subscriptions = Subscription::with(['account', 'product'])->get();
        return response()->json($subscriptions);
    }

    /**
     * Store a new subscription.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sub_id' => 'required|string|unique:subscriptions,sub_id',
            'status' => 'in:active,pending,canceled,suspended',
            'price' => 'required|numeric|min:0',
            'account_id' => 'required|exists:accounts,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $subscription = Subscription::create($validated);

        $cycle = $subscription->product->price->cycle ?? 'monthly';
        $nextCharge = match ($cycle) {
            'daily'     => now()->addDay(),
            'weekly'    => now()->addWeek(),
            'monthly'   => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly'    => now()->addYear(),
            default     => null,
        };

        $subscription->update([
            'last_charged_at' => now(),
            'next_charge_date' => $nextCharge,
        ]);

        return response()->json([
            'message' => 'Subscription created successfully',
            'data' => $subscription
        ], 201);
    }

    /**
     * Display a single subscription.
     */
    public function show($id)
    {
        $subscription = Subscription::with(['account', 'product'])->find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        return response()->json($subscription);
    }

    /**
     * Update an existing subscription.
     */
    public function update(Request $request, $id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        $validated = $request->validate([
            'sub_id' => 'sometimes|string|unique:subscriptions,sub_id,' . $id,
            'status' => 'sometimes|in:active,pending,canceled,suspended',
            'price' => 'sometimes|numeric|min:0',
            'account_id' => 'sometimes|exists:accounts,id',
            'product_id' => 'sometimes|exists:products,id',
        ]);

        $subscription->update($validated);

        return response()->json([
            'message' => 'Subscription updated successfully',
            'data' => $subscription
        ]);
    }

    /**
     * Delete a subscription.
     */
    public function destroy($id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully']);
    }
}
