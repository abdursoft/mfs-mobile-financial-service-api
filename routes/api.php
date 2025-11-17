<?php

use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\V1\Agent\AgentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Docs\KycController;
use App\Http\Controllers\Api\V1\Docs\NidController;
use App\Http\Controllers\Api\V1\Merchant\MerchantController;
use App\Http\Controllers\Api\V1\Merchant\MerchantCredentialController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Price\PriceController;
use App\Http\Controllers\Api\V1\Product\ProductController;
use App\Http\Controllers\Api\V1\Transaction\TransactionController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\WalletController;
use App\Http\Controllers\Api\V1\Webhook\WebhookController;
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
        Route::get('get-app', [MerchantCredentialController::class, 'merchantShow']);
        Route::post('delete-app/{id}', [MerchantCredentialController::class, 'destroy']);
        Route::get('dashboard', [MerchantController::class, 'dashboard']);
    });

    // merchant pgw routes
    Route::middleware('auth.pgw')->group(function(){
        // Price routes
        Route::prefix('price')->group(function(){
            Route::post('create', [PriceController::class, 'store'])->name('price.create');
            Route::get('list/{id?}', [PriceController::class, 'show'])->name('price.list');
            Route::post('update/{id}', [PriceController::class, 'update'])->name('price.update');
            Route::delete('delete/{id}', [PriceController::class, 'destroy'])->name('price.delete');
        });

        // Product routes
        Route::prefix('product')->group(function(){
            Route::post('create', [ProductController::class, 'store'])->name('product.create');
            Route::get('list/{id?}', [ProductController::class, 'show'])->name('product.list');
            Route::post('update/{id}', [ProductController::class, 'update'])->name('product.update');
            Route::delete('delete/{id}', [ProductController::class, 'destroy'])->name('product.delete');
        });

        // webhook routes
        Route::prefix('webhook')->group(function(){
            Route::post('create', [WebhookController::class, 'store'])->name('webhook.create');
            Route::get('list/{id?}', [WebhookController::class, 'show'])->name('webhook.list');
            Route::post('update/{id}', [WebhookController::class, 'update'])->name('webhook.update');
            Route::delete('delete/{id}', [WebhookController::class, 'destroy'])->name('webhook.delete');
        });
    });

    // token routes
    Route::prefix('token')->group(function(){
        Route::post('grant', [PaymentController::class, 'createToken']);
        Route::post('refresh', [PaymentController::class, 'createToken']);
    });

    // procedural routes
    Route::prefix('process')->group(function(){
        Route::post('otp/verify/{id}', [PaymentController::class, 'checkOTP']);
        Route::post('otp/new/{id}', [PaymentController::class, 'resendOTP']);
        Route::post('pin/verify/{id}', [PaymentController::class, 'checkPIN']);
    });

    // payment routes
    Route::prefix('payment')->group(function () {
        Route::post('create', [PaymentController::class, 'createPayment'])->middleware(PaymentMiddleware::class);
        Route::post('proceed/{id}', [PaymentController::class, 'proceedPayment']);
        Route::get('fetch/{id?}', [PaymentController::class, 'getMerchantPayment'])->middleware(PaymentMiddleware::class);

        // payment details
        Route::get('merchant/{id}', [PaymentController::class, 'merchantByPayment']);
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
