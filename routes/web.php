<?php

use App\Http\Controllers\Api\authController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('google/login',[authController::class, 'redirectGoogle']);
Route::get('auth/google/callback',[authController::class, 'social_login']);
