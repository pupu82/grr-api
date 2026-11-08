<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes are loaded with the /api prefix automatically.
| All authenticated routes use auth:sanctum + active middleware.
| Role-based access is enforced with the role middleware.
|
*/

// Public routes (login & register) — no auth required
// Route::post('/v1/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
// Route::post('/v1/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// Authenticated routes — user must be logged in and active
Route::middleware(['auth:sanctum', 'active'])->prefix('v1')->group(function () {

    // Any authenticated & active user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin only — manages users, master data, sessions
    Route::middleware('role:admin')->group(function () {
        // Route::apiResource('users', UserController::class);
        // Route::apiResource('plants', PlantController::class);
        // Route::apiResource('part-types', PartTypeController::class);
        // Route::apiResource('samples', SampleController::class);
        // Route::apiResource('teams', TeamController::class);
        // Route::apiResource('sessions', InspectionSessionController::class);
    });

    // Admin & Manager — view reports and results
    Route::middleware('role:admin,manager')->group(function () {
        // Route::get('/reports', [ReportController::class, 'index']);
        // Route::get('/evaluations', [EvaluationController::class, 'index']);
    });

    // Inspector — run inspections and view own results
    Route::middleware('role:inspector')->group(function () {
        // Route::post('/inspections', [InspectionController::class, 'store']);
        // Route::get('/my-results', [InspectionController::class, 'myResults']);
    });
});
