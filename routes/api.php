<?php

use App\Http\Controllers\Api\SelfServiceProfileController;
use App\Http\Controllers\Api\ClientUserAccessController;
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

Route::middleware(['web', 'auth'])
    ->controller(SelfServiceProfileController::class)
    ->prefix('/profile')
    ->name('profile.')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'update')->name('update');
        Route::patch('/password', 'updatePassword')->name('password.update');
    }
);

Route::middleware(['web', 'auth'])->group(function () {
    Route::controller(ClientUserAccessController::class)->name('api.client-user-access.')->group(function () {
        Route::get('/client-user-access', 'index')->name('index');
        Route::post('/client-user-access', 'store')->name('store');
        Route::delete('/client-user-access', 'bulkDestroy')->name('bulk-destroy');
        Route::put('/client-user-access/{clientUserAccess}', 'update')->name('update');
        Route::delete('/client-user-access/{clientUserAccess}', 'destroy')->name('destroy');
    });

    Route::get('/sso-clients/{ssoClient}/user-accesses', [ClientUserAccessController::class, 'clientAccesses'])
        ->name('api.sso-clients.user-accesses');
    Route::get('/users/{user}/client-accesses', [ClientUserAccessController::class, 'userAccesses'])
        ->name('api.users.client-accesses');
});
