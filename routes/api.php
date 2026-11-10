<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectionResultController;
use App\Http\Controllers\InspectionSessionController;
use App\Http\Controllers\PartTypeController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SampleKeyHistoryController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Models\InspectionSession;
use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\SampleKeyHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes are loaded with the /api prefix automatically. Public routes are
| throttled. Every other route uses auth:sanctum + active; authorization is
| enforced with policies, never in the Flutter app. Accounts that still use a
| temporary password can only read their profile, log out and change it.
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1,register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1,login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/password', [AuthController::class, 'changePassword']);

        Route::middleware('password.changed')->group(function () {
            // Users
            Route::get('/users', [UserController::class, 'index'])->can('viewAny', User::class);
            Route::post('/users', [UserController::class, 'store'])->can('create', User::class);
            Route::get('/users/{user}', [UserController::class, 'show'])->can('view', 'user');
            Route::post('/users/{user}/approve', [UserController::class, 'approve'])->can('approve', 'user');
            Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->can('deactivate', 'user');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->can('delete', 'user');

            // Plants
            Route::get('/plants', [PlantController::class, 'index'])->can('viewAny', Plant::class);
            Route::post('/plants', [PlantController::class, 'store'])->can('create', Plant::class);
            Route::get('/plants/{plant}', [PlantController::class, 'show'])->can('view', 'plant');
            Route::match(['put', 'patch'], '/plants/{plant}', [PlantController::class, 'update'])->can('update', 'plant');
            Route::delete('/plants/{plant}', [PlantController::class, 'destroy'])->can('delete', 'plant');

            // Part types
            Route::get('/part-types', [PartTypeController::class, 'index'])->can('viewAny', PartType::class);
            Route::post('/part-types', [PartTypeController::class, 'store'])->can('create', PartType::class);
            Route::get('/part-types/{part_type}', [PartTypeController::class, 'show'])->can('view', 'part_type');
            Route::match(['put', 'patch'], '/part-types/{part_type}', [PartTypeController::class, 'update'])->can('update', 'part_type');
            Route::delete('/part-types/{part_type}', [PartTypeController::class, 'destroy'])->can('delete', 'part_type');

            // Teams
            Route::get('/teams', [TeamController::class, 'index'])->can('viewAny', Team::class);
            Route::post('/teams', [TeamController::class, 'store'])->can('create', Team::class);
            Route::get('/teams/{team}', [TeamController::class, 'show'])->can('view', 'team');
            Route::match(['put', 'patch'], '/teams/{team}', [TeamController::class, 'update'])->can('update', 'team');
            Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->can('delete', 'team');

            // Samples
            Route::get('/samples', [SampleController::class, 'index'])->can('viewAny', Sample::class);
            Route::post('/samples', [SampleController::class, 'store'])->can('create', Sample::class);
            Route::get('/samples/{sample}', [SampleController::class, 'show'])->can('view', 'sample');
            Route::match(['put', 'patch'], '/samples/{sample}', [SampleController::class, 'update'])->can('update', 'sample');
            Route::delete('/samples/{sample}', [SampleController::class, 'destroy'])->can('delete', 'sample');
            Route::get('/samples/{sample}/key-histories', [SampleKeyHistoryController::class, 'index'])
                ->can('viewAny', SampleKeyHistory::class);

            // Inspection sessions
            Route::get('/sessions', [InspectionSessionController::class, 'index'])->can('viewAny', InspectionSession::class);
            Route::post('/sessions', [InspectionSessionController::class, 'store'])->can('create', InspectionSession::class);
            Route::get('/sessions/{session}', [InspectionSessionController::class, 'show'])->can('view', 'session');
            Route::post('/sessions/{session}/start', [InspectionSessionController::class, 'start'])->can('start', 'session');
            Route::post('/sessions/{session}/start-run', [InspectionSessionController::class, 'startRun'])->can('startRun', 'session');
            Route::get('/sessions/{session}/current-sample', [InspectionSessionController::class, 'currentSample'])->can('viewCurrentSample', 'session');
            Route::post('/sessions/{session}/results', [InspectionResultController::class, 'store'])->can('submitResult', 'session');
            Route::post('/sessions/{session}/close', [InspectionSessionController::class, 'close'])->can('close', 'session');
            Route::delete('/sessions/{session}', [InspectionSessionController::class, 'destroy'])->can('delete', 'session');
        });
    });
});
