<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Models\User;
use App\Models\Transaction;
use App\Notifications\KycStatusNotification;
use App\Traits\MessageHandler;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use MessageHandler;
    /**
     * List pending KYC requests.
     */
    public function pendingKycs($id=null)
    {
        if($id){
            $kycs = Kyc::findOrFail($id);
        }else{
            $kycs = Kyc::where('status', 'pending')->with(['user' => function($query){
                $query->select('id','name','phone','role','image','phone_verified_at','created_at');
            }])->latest()->get();
        }
        return response()->json([
            'code' => 'KYC_RETRIEVED',
            'message' => 'KYC successfully retrieved',
            'data' => $kycs
        ],200);
    }

    /**
     * Approve a KYC.
     */
    public function approveKyc($id)
    {
        $kyc = Kyc::findOrFail($id);
        $kyc->status = 'approved';
        $kyc->save();

        $app = env('APP_NAME');

        // Notify the user
        $this->smsInit("{$kyc->user->name} Your KYC has been approved and remove your transaction limit. Thanks for using {$app}",'KYC Approved',$kyc->user->phone,null,$kyc->user->name);

        return response()->json([
            'code' => 'KYC_APPROVED',
            'message' => 'Your KYC has been approved'
        ]);
    }

    /**
     * Reject a KYC.
     */
    public function rejectKyc($id)
    {
        $kyc = Kyc::findOrFail($id);
        $kyc->status = 'rejected';
        $kyc->save();

        // Notify the user
        $this->smsInit("{$kyc->user->name} Your KYC has been rejected, Please try again later",'KYC Approved',$kyc->user->phone,null,$kyc->user->name);

        return response()->json([
            'code' => 'KYC_REJECTED',
            'message' => 'Your KYC has been rejected'
        ]);
    }

    /**
     * Basic dashboard: total users & transactions.
     */
    public function dashboard()
    {
        $totalUsers = User::count();
        $totalTransactions = Transaction::count();
        $totalTransactionAmount = Transaction::where('status', 'completed')->sum('amount');

        return response()->json([
            'total_users' => $totalUsers,
            'total_transactions' => $totalTransactions,
            'total_transaction_amount' => $totalTransactionAmount,
        ]);
    }
}
