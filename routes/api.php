<?php

use App\Http\Controllers\OAuth\OAuthIntrospectController;
use App\Http\Controllers\OAuth\OAuthRevokeController;
use App\Http\Controllers\OAuth\OAuthUserInfoController;
use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', TokenController::class)
    ->middleware('throttle:oauth-token')
    ->name('oauth.token');

Route::post('/oauth/revoke', OAuthRevokeController::class)
    ->middleware('throttle:oauth-client')
    ->name('oauth.revoke');

Route::post('/oauth/introspect', OAuthIntrospectController::class)
    ->middleware('throttle:oauth-client')
    ->name('oauth.introspect');

Route::get('/oauth/userinfo', OAuthUserInfoController::class)
    ->middleware('throttle:oauth-userinfo')
    ->name('oauth.userinfo');
