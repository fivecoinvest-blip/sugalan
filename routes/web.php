<?php

use Illuminate\Support\Facades\Route;

// Admin Dashboard SPA
Route::get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*');

// Player SPA - catch all routes except /admin
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '(?!admin).*');
