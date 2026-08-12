<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'PocketFlow API',
        'status' => 'running',
        'message' => 'Use /api endpoints from the PWA frontend.',
    ]);
});
