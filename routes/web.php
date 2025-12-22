<?php

use Illuminate\Support\Facades\Route;

// Legal pages
Route::get('/legal/terms-of-service', function () {
    return view('legal.terms-of-service');
});

Route::get('/legal/privacy-policy', function () {
    return view('legal.privacy-policy');
});

Route::get('/legal/responsible-gaming', function () {
    return view('legal.responsible-gaming');
});

Route::get('/legal/cookie-policy', function () {
    return view('legal.cookie-policy');
});

// Admin Dashboard SPA
Route::get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*');

// Player SPA - catch all routes except /admin
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '(?!admin).*');
