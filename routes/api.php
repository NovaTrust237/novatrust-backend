<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\GrantController;
use App\Http\Controllers\AdminController;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhook/payment', [PaymentController::class, 'handleWebhook'])->name('webhook.payment');

// Authenticated client
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/transactions', [UserController::class, 'transactions']);
    Route::put('/profile', [UserController::class, 'updateProfile']);

    // Scalping / investissement
    Route::post('/activate', [PaymentController::class, 'activate']);
    Route::post('/invest', [PaymentController::class, 'invest']);
    Route::post('/finalize-investment', [PaymentController::class, 'finalizeInvestment']);
    Route::post('/stop-investment', [PaymentController::class, 'stopInvestment']);
    Route::get('/investment-status', [PaymentController::class, 'investmentStatus']);

    // Retraits (manuels via WhatsApp)
    Route::post('/withdrawals/request', [WithdrawalController::class, 'request']);

    // Prêts bancaires
    Route::post('/loans/simulate', [LoanController::class, 'simulate']);
    Route::post('/loans/apply', [LoanController::class, 'apply']);
    Route::get('/loans/mine', [LoanController::class, 'mine']);

    // Subventions
    Route::post('/grants/apply', [GrantController::class, 'apply']);
    Route::get('/grants/mine', [GrantController::class, 'mine']);
});

// Admin only
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/clients', [AdminController::class, 'clients']);
    Route::get('/clients/{id}', [AdminController::class, 'showClient']);
    Route::post('/clients/{id}/balance', [AdminController::class, 'adjustBalance']);
    Route::post('/clients/{id}/toggle-activation', [AdminController::class, 'toggleActivation']);

    Route::get('/loans', [AdminController::class, 'loanApplications']);
    Route::post('/loans/{id}/status', [AdminController::class, 'updateLoanStatus']);

    Route::get('/grants', [AdminController::class, 'grantApplications']);
    Route::post('/grants/{id}/status', [AdminController::class, 'updateGrantStatus']);

    Route::get('/scalpings/active', [AdminController::class, 'activeScalpings']);

    // Admin management
    Route::post('/admins', [AdminController::class, 'createAdmin']);
    Route::get('/admins', [AdminController::class, 'listAdmins']);
});
