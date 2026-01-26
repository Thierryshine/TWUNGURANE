<?php

/**
 * TWUNGURANE - Routes API
 * 
 * Toutes les routes API REST pour l'application
 * Préfixe automatique: /api
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AnalyticsController;

/*
|--------------------------------------------------------------------------
| Routes publiques (sans authentification)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Santé de l'API
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'TWUNGURANE API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    });

});

/*
|--------------------------------------------------------------------------
| Routes protégées (authentification requise)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // =========================================================================
    // Authentification - Actions utilisateur connecté
    // =========================================================================
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::post('/kyc/upload', [AuthController::class, 'uploadKycDocument']);
    });

    // =========================================================================
    // Groupes d'épargne (VSLA / Tontines)
    // =========================================================================
    Route::apiResource('groups', GroupController::class);
    
    Route::prefix('groups/{group}')->group(function () {
        // Statistiques du groupe
        Route::get('/statistics', [GroupController::class, 'statistics']);
        Route::get('/balance', [GroupController::class, 'balance']);
        
        // Membres du groupe
        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::get('/members/{member}', [MemberController::class, 'show']);
        Route::put('/members/{member}', [MemberController::class, 'update']);
        Route::delete('/members/{member}', [MemberController::class, 'destroy']);
        Route::put('/members/{member}/role', [MemberController::class, 'updateRole'])
            ->middleware('role:admin,tresorier');
        
        // Contributions du groupe
        Route::get('/contributions', [ContributionController::class, 'indexByGroup']);
        Route::post('/contributions', [ContributionController::class, 'store'])
            ->middleware('role:admin,tresorier');
        
        // Prêts du groupe
        Route::get('/loans', [LoanController::class, 'indexByGroup']);
        Route::post('/loans', [LoanController::class, 'store']);
        
        // Transactions du groupe
        Route::get('/transactions', [TransactionController::class, 'indexByGroup']);
    });

    // =========================================================================
    // Contributions (toutes)
    // =========================================================================
    Route::prefix('contributions')->group(function () {
        Route::get('/', [ContributionController::class, 'index']);
        Route::get('/{contribution}', [ContributionController::class, 'show']);
        Route::put('/{contribution}', [ContributionController::class, 'update'])
            ->middleware('role:admin,tresorier');
        Route::delete('/{contribution}', [ContributionController::class, 'destroy'])
            ->middleware('role:admin');
    });

    // =========================================================================
    // Prêts VSLA
    // =========================================================================
    Route::prefix('loans')->group(function () {
        Route::get('/', [LoanController::class, 'index']);
        Route::get('/{loan}', [LoanController::class, 'show']);
        Route::put('/{loan}/approve', [LoanController::class, 'approve'])
            ->middleware('role:admin,tresorier');
        Route::put('/{loan}/reject', [LoanController::class, 'reject'])
            ->middleware('role:admin,tresorier');
        Route::post('/{loan}/repay', [LoanController::class, 'repay']);
        Route::get('/{loan}/schedule', [LoanController::class, 'repaymentSchedule']);
    });

    // =========================================================================
    // Transactions
    // =========================================================================
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{transaction}', [TransactionController::class, 'show']);
    });

    // =========================================================================
    // Rapports
    // =========================================================================
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/group/{group}', [ReportController::class, 'groupReport']);
        Route::get('/member/{member}', [ReportController::class, 'memberReport']);
        Route::get('/contributions/summary', [ReportController::class, 'contributionsSummary']);
        Route::get('/loans/summary', [ReportController::class, 'loansSummary']);
        Route::get('/export/{type}', [ReportController::class, 'export']);
    });

    // =========================================================================
    // Analytics (proxy vers Python service)
    // =========================================================================
    Route::prefix('analytics')->group(function () {
        Route::post('/risk-score', [AnalyticsController::class, 'riskScore']);
        Route::post('/financial-projection', [AnalyticsController::class, 'financialProjection']);
        Route::get('/group/{group}/health', [AnalyticsController::class, 'groupHealth']);
        Route::get('/group/{group}/member-ranking', [AnalyticsController::class, 'memberRanking']);
        Route::post('/cycle-simulation', [AnalyticsController::class, 'cycleSimulation']);
    });

});

/*
|--------------------------------------------------------------------------
| Routes Admin (rôle admin uniquement)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    
    // Gestion des utilisateurs
    Route::get('/users', [AuthController::class, 'listUsers']);
    Route::put('/users/{user}/status', [AuthController::class, 'updateUserStatus']);
    Route::put('/users/{user}/role', [AuthController::class, 'updateUserRole']);
    
    // Statistiques globales
    Route::get('/statistics', [ReportController::class, 'adminStatistics']);
    
    // Audit logs
    Route::get('/audit-logs', [ReportController::class, 'auditLogs']);
    
});
