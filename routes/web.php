<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for every non-API route so Vue Router can handle navigation.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum).*$');
