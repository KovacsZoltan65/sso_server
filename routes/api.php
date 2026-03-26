<?php

use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', TokenController::class)->name('oauth.token');
