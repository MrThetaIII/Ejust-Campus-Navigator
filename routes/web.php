<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\UserMapController;
use App\Models\Location;
use Illuminate\Support\Facades\Route;

// Redirect root to main campus
Route::get('/', function () {
    return redirect('/map/main-campus');
});

// User routes with location
Route::prefix('map/{locationCode}')->group(function () {
    Route::get('/', [UserMapController::class, 'index'])->name('map.user');
    Route::get('/admin', [MapController::class, 'index'])->name('map.admin');
});

// Admin API routes with location
Route::prefix('api/{locationCode}')->group(function () {
    Route::get('/map-data', [MapController::class, 'getMapData']);
    Route::post('/hops', [MapController::class, 'saveHop']);
    Route::post('/connections', [MapController::class, 'connectHops']);
    Route::post('/find-path', [MapController::class, 'findPath']);
    Route::post('/boundary', [MapController::class, 'saveBoundary']);
    Route::post('/overlay', [MapController::class, 'uploadOverlay']);
    Route::delete('/hops/{hop}', [MapController::class, 'deleteHop']);
    Route::delete('/connections/{connection}', [MapController::class, 'deleteConnection']);
});

// User API routes with location
Route::prefix('user/api/{locationCode}')->group(function () {
    Route::get('/map-data', [UserMapController::class, 'getMapData']);
    Route::post('/find-path', [UserMapController::class, 'findPath']);
});

// Fallback for debugging
Route::get('/debug/routes', function() {
    return response()->json([
        'message' => 'Routes are working',
        'timestamp' => now()
    ]);
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
