<?php

use App\Http\Controllers\Api\EmailController;
use App\Models\SuccessfulEmail;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', function() {
    $user = \App\Models\User::first();
    if (!$user) {
        return response()->json(['error' => 'No users'], 404);
    }

    return response()->json([
        'token' => $user->createToken('api-token')->plainTextToken,
        'token_type' => 'Bearer'
    ]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('emails', EmailController::class);

    Route::get('emails/unprocessed/count', function() {
        return response()->json([
            'count' => SuccessfulEmail::unprocessed()->count()
        ]);
    });
});
