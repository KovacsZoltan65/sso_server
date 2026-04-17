<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientUserAccessPageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlaceholderPageController;
use App\Http\Controllers\Admin\RememberedConsentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ScopeController;
use App\Http\Controllers\Admin\TokenController as AdminTokenController;
use App\Http\Controllers\Admin\TokenPolicyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OAuth\AuthorizationController;
use App\Http\Controllers\OAuth\OidcDiscoveryController;
use App\Http\Controllers\OAuth\OidcEndSessionController;
use App\Http\Controllers\OAuth\OidcJwksController;
use App\Http\Controllers\OAuth\OAuthConsentApproveController;
use App\Http\Controllers\OAuth\OAuthConsentDenyController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Nyelv választó
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/.well-known/openid-configuration', OidcDiscoveryController::class)->name('oidc.discovery');
Route::get('/.well-known/jwks.json', OidcJwksController::class)->name('oidc.jwks');
Route::get('/oidc/logout', OidcEndSessionController::class)->name('oidc.end_session');

Route::middleware(['auth'])->group(function () {
    Route::get('/oauth/authorize', AuthorizationController::class)->name('oauth.authorize');
    Route::post('/oauth/authorize/approve', OAuthConsentApproveController::class)->name('oauth.authorize.approve');
    Route::post('/oauth/authorize/deny', OAuthConsentDenyController::class)->name('oauth.authorize.deny');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {

        // Users routes
        Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('destroy');
        });

        // Roles routes
        Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/{role}/edit', 'edit')->name('edit');
            Route::put('/{role}', 'update')->name('update');
            Route::delete('/{role}', 'destroy')->name('destroy');
        });

        // Permissions routes
        Route::controller(PermissionController::class)->prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/{permission}/edit', 'edit')->name('edit');
            Route::put('/{permission}', 'update')->name('update');
            Route::delete('/{permission}', 'destroy')->name('destroy');
        });

        // SSO Clients routes
        Route::controller(ClientController::class)->prefix('sso-clients')->name('sso-clients.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{ssoClient}/edit', 'edit')->name('edit');
            Route::put('/{ssoClient}', 'update')->name('update');
            Route::post('/{ssoClient}/rotate-secret', 'rotateSecret')->name('rotate-secret');

            Route::delete('/{ssoClient}/secrets/{clientSecret}', 'revokeSecret')->name('revoke-secret');
            Route::delete('/{ssoClient}', 'destroy')->name('destroy');
        });

        // Client - User Access routes
        Route::controller(ClientUserAccessPageController::class)->prefix('client-user-access')->name('client-user-access.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{clientUserAccess}/edit', 'edit')->name('edit');
            Route::put('/{clientUserAccess}', 'update')->name('update');
        });

        // Scopes routes
        Route::controller(ScopeController::class)->name('scopes.')->group(function () {
            Route::get('/scopes', 'index')->name('index');
            Route::get('/scopes/create', 'create')->name('create');
            Route::post('/scopes', 'store')->name('store');
            Route::delete('/scopes', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/scopes/{scope}/edit', 'edit')->name('edit');
            Route::put('/scopes/{scope}', 'update')->name('update');
            Route::delete('/scopes/{scope}', 'destroy')->name('destroy');
        });

        // Token Poicies routes
        Route::controller(TokenPolicyController::class)->prefix('token-policies')->name('token-policies.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::delete('/', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/{tokenPolicy}/edit', 'edit')->name('edit');
            Route::put('/{tokenPolicy}', 'update')->name('update');
            Route::delete('/{tokenPolicy}', 'destroy')->name('destroy');
        });

        // Tokens routes
        Route::controller(AdminTokenController::class)->prefix('tokens')->name('tokens.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{token}/revoke', 'revoke')->name('revoke');
            Route::post('/families/{familyId}/revoke', 'revokeFamily')->name('revoke-family');
        });

        // Remember Consents
        Route::controller(RememberedConsentController::class)->prefix('remembered-consents')->name('remembered-consents.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{consent}/revoke', 'revoke')->name('revoke');
        });

        // PlaceholderPageController routes
        Route::controller(PlaceholderPageController::class)->prefix('audit-logs')->name('audit-logs.')->group(function () {
            Route::get('/', 'auditLogs')->name('index');
        });
    });

    // ProfileController routes
    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });
});

require __DIR__.'/auth.php';
