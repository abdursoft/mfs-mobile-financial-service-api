<?php

use App\Http\Controllers\Api\Bank\BankController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BankController::class,'bank']);
