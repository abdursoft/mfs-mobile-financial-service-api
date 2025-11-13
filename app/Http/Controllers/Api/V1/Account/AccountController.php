<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    // Get all accounts
    public function index()
    {
        return response()->json(Account::all(), 200);
    }

    // Create a new account
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string',
            'email'    => 'nullable|email',
            'phone'    => 'required|string',
            'country'  => 'required|string',
            'province' => 'nullable|string',
            'state'    => 'nullable|string',
            'district' => 'required|string',
            'zipcode'  => 'required|string',
            'village'  => 'nullable|string',
        ]);

        $validated['act_id'] = actID(Account::class,'act_id',16);

        $account = Account::create($validated);

        return response()->json([
            'message' => 'Account created successfully',
            'data' => $account
        ], 201);
    }

    // Get a single account
    public function show($id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        return response()->json($account);
    }

    // Update an account
    public function update(Request $request, $id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $validated = $request->validate([
            'act_id'   => 'sometimes|string',
            'name'     => 'sometimes|string',
            'email'    => 'nullable|email',
            'phone'    => 'sometimes|string',
            'country'  => 'sometimes|string',
            'province' => 'nullable|string',
            'state'    => 'nullable|string',
            'district' => 'sometimes|string',
            'zipcode'  => 'sometimes|string',
            'village'  => 'nullable|string',
        ]);

        $account->update($validated);

        return response()->json([
            'message' => 'Account updated successfully',
            'data' => $account
        ]);
    }

    // Delete an account
    public function destroy($id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
