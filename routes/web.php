<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\UserMapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index']);
Route::get('/api/map-data', [MapController::class, 'getMapData']);
Route::post('/api/hops', [MapController::class, 'saveHop']);
Route::post('/api/connections', [MapController::class, 'connectHops']);
Route::post('/api/find-path', [MapController::class, 'findPath']);
Route::post('/api/boundary', [MapController::class, 'saveBoundary']);
Route::post('/api/overlay', [MapController::class, 'uploadOverlay']);
Route::delete('/api/hops/{hop}', [MapController::class, 'deleteHop']);
Route::delete('/api/connections/{connection}', [MapController::class, 'deleteConnection']);
Route::get('/api/current-location', [MapController::class, 'getCurrentLocation']);

// User routes
Route::prefix('user')->group(function () {
    Route::get('/', [UserMapController::class, 'index'])->name('user.map');
    Route::get('/api/map-data', [UserMapController::class, 'getMapData']);
    Route::post('/api/find-path', [UserMapController::class, 'findPath']);
});
