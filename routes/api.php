<?php

use App\Http\Controllers\OAuth\OAuthIntrospectController;
use App\Http\Controllers\OAuth\OAuthRevokeController;
use App\Http\Controllers\OAuth\OAuthUserInfoController;
use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', TokenController::class)
    ->name('oauth.token');

Route::post('/oauth/revoke', OAuthRevokeController::class)
    ->name('oauth.revoke');

Route::post('/oauth/introspect', OAuthIntrospectController::class)
    ->name('oauth.introspect');

Route::get('/oauth/userinfo', OAuthUserInfoController::class)
    ->name('oauth.userinfo');
