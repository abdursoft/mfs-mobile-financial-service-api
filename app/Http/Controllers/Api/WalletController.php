<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Get authenticated user's wallet balance.
     */
    public function balance(Request $request)
    {
        $wallet = $request->attributes->get('auth_user');

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        return response()->json([
            'code' => 'RETRIEVE_BALANCE',
            'balance' => $wallet->wallet->balance,
            'currency' => 'BDT', // or USD, or dynamic
        ],200);
    }

    /**
     * (Optional) Wallet statement / transactions.
     */
public function statement(Request $request)
{
    $user = $request->attributes->get('auth_user');

    $transactions = $user->transactionsFrom()
        ->latest()
        ->take(20)
        ->get();

    return response()->json($transactions);
}

}
