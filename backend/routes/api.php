<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GadgetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return response()->json([
            'data' => $request->user(),
        ]);
    });

    Route::get('/gadgets/{id}/price-histories', [GadgetController::class, 'priceHistories']);
    Route::apiResource('gadgets', GadgetController::class);
});
