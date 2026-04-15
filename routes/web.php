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
        Route::controller(UserController::class)->name('users.')->group(function () {
            Route::get('/users', 'index')->name('index');
            Route::post('/users', 'store')->name('store');
            Route::delete('/users', 'bulkDestroy')->name('bulk-destroy');
            Route::put('/users/{user}', 'update')->name('update');
            Route::delete('/users/{user}', 'destroy')->name('destroy');
        });

        // Roles routes
        Route::controller(RoleController::class)->name('roles.')->group(function () {
            Route::get('/roles', 'index')->name('index');
            Route::get('/roles/create', 'create')->name('create');
            Route::post('/roles', 'store')->name('store');
            Route::delete('/roles', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/roles/{role}/edit', 'edit')->name('edit');
            Route::put('/roles/{role}', 'update')->name('update');
            Route::delete('/roles/{role}', 'destroy')->name('destroy');
        });

        // Permissions routes
        Route::controller(PermissionController::class)->name('permissions.')->group(function () {
            Route::get('/permissions', 'index')->name('index');
            Route::get('/permissions/create', 'create')->name('create');
            Route::post('/permissions', 'store')->name('store');
            Route::delete('/permissions', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/permissions/{permission}/edit', 'edit')->name('edit');
            Route::put('/permissions/{permission}', 'update')->name('update');
            Route::delete('/permissions/{permission}', 'destroy')->name('destroy');
        });

        // SSO Clients routes
        Route::controller(ClientController::class)->name('sso-clients.')->group(function () {
            Route::get('/sso-clients', 'index')->name('index');
            Route::get('/sso-clients/create', 'create')->name('create');
            Route::post('/sso-clients', 'store')->name('store');
            Route::get('/sso-clients/{ssoClient}/edit', 'edit')->name('edit');
            Route::put('/sso-clients/{ssoClient}', 'update')->name('update');
            Route::post('/sso-clients/{ssoClient}/rotate-secret', 'rotateSecret')->name('rotate-secret');

            Route::delete('/sso-clients/{ssoClient}/secrets/{clientSecret}', 'revokeSecret')->name('revoke-secret');
            Route::delete('/sso-clients/{ssoClient}', 'destroy')->name('destroy');
        });

        // Client - User Access routes
        Route::controller(ClientUserAccessPageController::class)->name('client-user-access.')->group(function () {
            Route::get('/client-user-access', 'index')->name('index');
            Route::get('/client-user-access/create', 'create')->name('create');
            Route::post('/client-user-access', 'store')->name('store');
            Route::get('/client-user-access/{clientUserAccess}/edit', 'edit')->name('edit');
            Route::put('/client-user-access/{clientUserAccess}', 'update')->name('update');
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
        Route::controller(TokenPolicyController::class)->name('token-policies.')->group(function () {
            Route::get('/token-policies', 'index')->name('index');
            Route::get('/token-policies/create', 'create')->name('create');
            Route::post('/token-policies', 'store')->name('store');
            Route::delete('/token-policies', 'bulkDestroy')->name('bulk-destroy');
            Route::get('/token-policies/{tokenPolicy}/edit', 'edit')->name('edit');
            Route::put('/token-policies/{tokenPolicy}', 'update')->name('update');
            Route::delete('/token-policies/{tokenPolicy}', 'destroy')->name('destroy');
        });

        // Tokens routes
        Route::controller(AdminTokenController::class)->name('tokens.')->group(function () {
            Route::get('/tokens', 'index')->name('index');
            Route::post('/tokens/{token}/revoke', 'revoke')->name('revoke');
            Route::post('/tokens/families/{familyId}/revoke', 'revokeFamily')->name('revoke-family');
        });

        // Remember Consents
        Route::controller(RememberedConsentController::class)->name('remembered-consents.')->group(function () {
            Route::get('/remembered-consents', 'index')->name('index');
            Route::post('/remembered-consents/{consent}/revoke', 'revoke')->name('revoke');
        });

        // PlaceholderPageController routes
        Route::controller(PlaceholderPageController::class)->group(function () {
            Route::get('/audit-logs', 'auditLogs')->name('audit-logs.index');
        });
    });

    // ProfileController routes
    Route::controller(ProfileController::class)->name('profile.')->group(function () {
        Route::get('/profile', 'edit')->name('edit');
        Route::patch('/profile', 'update')->name('update');
        Route::delete('/profile', 'destroy')->name('destroy');
    });
});

require __DIR__.'/auth.php';
