<?php

use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\MerchantCredentialController;
use App\Http\Controllers\Api\NidController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\PaymentMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // auth routes
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('register', 'store');
        Route::post('verify-otp', 'verifySignupOTP');
        Route::post('resend-otp', 'resendOTP');
        Route::post('set-pin', 'setPin');
        Route::post('login', 'signin');
        Route::post('refresh-token', 'refresh');
        Route::get('check', 'checkAuthUser')->middleware('auth.jwt');
        Route::post('logout', 'signout')->middleware('auth.jwt');
    });

    // universal routes
    Route::middleware('auth.jwt')->prefix('user')->controller(UserController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('profile', 'profile');
        Route::post('update-profile', 'updateProfile');
        Route::post('change-pin', 'changePin');
        Route::post('change-password', 'changePassword');
        Route::post('reset-password', 'resetPassword');
    });

    // reset pin
    Route::prefix('pin')->group(function () {
        Route::post('reset', [UserController::class, 'pinReset']);
        Route::post('resend-otp', [UserController::class, 'resendPinResetOTP']);
        Route::post('verify-otp', [UserController::class, 'checkPinOTP']);
        Route::post('new-pin', [UserController::class, 'newPin']);
    });

    // wallet routes
    Route::middleware('auth.jwt')->prefix('wallet')->group(function () {
        Route::controller(WalletController::class)->group(function(){
            Route::get('balance', 'balance');
            Route::get('statement', 'statement');
        });

        Route::controller(TransactionController::class)->group(function(){
            Route::get('summery', 'summery');
        });
    });

    // transfer routes
    Route::middleware('auth.user')->prefix('transaction')->controller(TransactionController::class)->group(function () {
        Route::post('send', 'send');
        Route::post('cash-out', 'cashOut');
        Route::post('payment', 'onlinePayment');
    });

    // kyc routes
    Route::middleware('auth.jwt')->prefix('kyc')->controller(KycController::class)->group(function () {
        Route::post('upload', 'upload');
        Route::get('status', 'status');
    });

    // agent routes
    Route::middleware('auth.agent')->prefix('agent')->controller(AgentController::class)->group(function () {
        Route::post('cash-in', 'cashIn');
        Route::get('dashboard', 'dashboard');
    });

    // merchant routes
    Route::middleware('auth.merchant')->prefix('merchant')->group(function () {
        Route::post('receive-payment', [MerchantController::class, 'receivePayment']);
        Route::post('create-app', [MerchantCredentialController::class, 'store']);
        Route::post('get-app', [MerchantCredentialController::class, 'merchantShow']);
        Route::get('dashboard', [MerchantController::class, 'dashboard']);
    });

    // payment routes
    Route::prefix('payment')->group(function () {
        Route::post('token', [PaymentController::class, 'createToken']);
        Route::post('create', [PaymentController::class, 'createPayment'])->middleware(PaymentMiddleware::class);
        Route::post('proceed/{id}', [PaymentController::class, 'proceedPayment']);
        Route::post('otp/verify/{id}', [PaymentController::class, 'checkOTP']);
        Route::post('pin/verify/{id}', [PaymentController::class, 'checkPIN']);
    });

    // Sms routes
    Route::middleware('auth.admin')->prefix('sms-methods')->controller(SmsController::class)->group(function () {
        Route::get('/', 'smsMethods')->name('sms-method.methods');
        Route::post('create', 'store')->name('sms-method.add');
        Route::post('update', 'store')->name('sms-method.update');
        Route::delete('delete/{id}', 'destroy')->name('sms-method.delete');
        Route::get('open/{id?}', 'show')->name('sms-method.list');
        Route::post('set', 'activeSMS')->name('sms-method.methods.set');
        Route::get('active', 'getActiveSMS')->name('get.active.sms-method');
    });

    // admin routes
    Route::middleware('auth.admin')->prefix('admin')->group(function () {
        Route::get('kyc/{id?}', [AdminController::class, 'pendingKycs']);
        Route::post('kyc/approve/{id}', [AdminController::class, 'approveKyc']);
        Route::post('kyc/reject/{id}', [AdminController::class, 'rejectKyc']);
        Route::get('dashboard', [AdminController::class, 'dashboard']);
    });
});

Route::post('/nid/upload', [NidController::class, 'upload']);
