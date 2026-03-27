<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlaceholderPageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ScopeController;
use App\Http\Controllers\Admin\TokenPolicyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\OAuth\AuthorizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/oauth/authorize', AuthorizationController::class)->name('oauth.authorize');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {

        // UserController routes
        Route::controller(UserController::class)->name('users.')->group(function () {
            Route::get('/users', 'index')->middleware(PermissionMiddleware::using('users.viewAny'))->name('index');
            Route::post('/users', 'store')->middleware(PermissionMiddleware::using('users.create'))->name('store');
            Route::delete('/users', 'bulkDestroy')->middleware(PermissionMiddleware::using('users.deleteAny'))->name('bulk-destroy');
            Route::put('/users/{user}', 'update')->middleware(PermissionMiddleware::using('users.update'))->name('update');
            Route::delete('/users/{user}', 'destroy')->middleware(PermissionMiddleware::using('users.delete'))->name('destroy');
        });

        // RoleController routes
        Route::controller(RoleController::class)->name('roles.')->group(function () {
            Route::get('/roles', 'index')->middleware(PermissionMiddleware::using('roles.viewAny'))->name('index');
            Route::get('/roles/create', 'create')->middleware(PermissionMiddleware::using('roles.create'))->name('create');
            Route::post('/roles', 'store')->middleware(PermissionMiddleware::using('roles.create'))->name('store');
            Route::delete('/roles', 'bulkDestroy')->middleware(PermissionMiddleware::using('roles.deleteAny'))->name('bulk-destroy');
            Route::get('/roles/{role}/edit', 'edit')->middleware(PermissionMiddleware::using('roles.update'))->name('edit');
            Route::put('/roles/{role}', 'update')->middleware(PermissionMiddleware::using('roles.update'))->name('update');
            Route::delete('/roles/{role}', 'destroy')->middleware(PermissionMiddleware::using('roles.delete'))->name('destroy');
        });

        // PermissionController routes
        Route::controller(PermissionController::class)->name('permissions.')->group(function () {
            Route::get('/permissions', 'index')->middleware(PermissionMiddleware::using('permissions.viewAny'))->name('index');
            Route::get('/permissions/create', 'create')->middleware(PermissionMiddleware::using('permissions.create'))->name('create');
            Route::post('/permissions', 'store')->middleware(PermissionMiddleware::using('permissions.create'))->name('store');
            Route::delete('/permissions', 'bulkDestroy')->middleware(PermissionMiddleware::using('permissions.deleteAny'))->name('bulk-destroy');
            Route::get('/permissions/{permission}/edit', 'edit')->middleware(PermissionMiddleware::using('permissions.update'))->name('edit');
            Route::put('/permissions/{permission}', 'update')->middleware(PermissionMiddleware::using('permissions.update'))->name('update');
            Route::delete('/permissions/{permission}', 'destroy')->middleware(PermissionMiddleware::using('permissions.delete'))->name('destroy');
        });

        // ClientController routes
        Route::controller(ClientController::class)->name('sso-clients.')->group(function () {
            Route::get('/sso-clients', 'index')->middleware(PermissionMiddleware::using('clients.viewAny'))->name('index');
            Route::get('/sso-clients/create', 'create')->middleware(PermissionMiddleware::using('clients.create'))->name('create');
            Route::post('/sso-clients', 'store')->middleware(PermissionMiddleware::using('clients.create'))->name('store');
            Route::get('/sso-clients/{ssoClient}/edit', 'edit')->middleware(PermissionMiddleware::using('clients.update'))->name('edit');
            Route::put('/sso-clients/{ssoClient}', 'update')->middleware(PermissionMiddleware::using('clients.update'))->name('update');
            Route::post('/sso-clients/{ssoClient}/rotate-secret', 'rotateSecret')
                ->middleware(PermissionMiddleware::using('clients.rotateSecret|clients.manageSecrets'))
                ->name('rotate-secret');

            Route::delete('/sso-clients/{ssoClient}/secrets/{clientSecret}', 'revokeSecret')->middleware(PermissionMiddleware::using('clients.revokeSecret|clients.manageSecrets'))->name('revoke-secret');
            Route::delete('/sso-clients/{ssoClient}', 'destroy')->middleware(PermissionMiddleware::using('clients.delete'))->name('destroy');
        });

        // ScopeController routes
        Route::controller(ScopeController::class)->name('scopes.')->group(function () {
            Route::get('/scopes', 'index')->middleware(PermissionMiddleware::using('scopes.viewAny'))->name('index');
            Route::get('/scopes/create', 'create')->middleware(PermissionMiddleware::using('scopes.create'))->name('create');
            Route::post('/scopes', 'store')->middleware(PermissionMiddleware::using('scopes.create'))->name('store');
            Route::delete('/scopes', 'bulkDestroy')->middleware(PermissionMiddleware::using('scopes.deleteAny'))->name('bulk-destroy');
            Route::get('/scopes/{scope}/edit', 'edit')->middleware(PermissionMiddleware::using('scopes.update'))->name('edit');
            Route::put('/scopes/{scope}', 'update')->middleware(PermissionMiddleware::using('scopes.update'))->name('update');
            Route::delete('/scopes/{scope}', 'destroy')->middleware(PermissionMiddleware::using('scopes.delete'))->name('destroy');
        });

        Route::controller(TokenPolicyController::class)->name('token-policies.')->group(function () {
            Route::get('/token-policies', 'index')->middleware(PermissionMiddleware::using('token-policies.viewAny'))->name('index');
            Route::get('/token-policies/create', 'create')->middleware(PermissionMiddleware::using('token-policies.create'))->name('create');
            Route::post('/token-policies', 'store')->middleware(PermissionMiddleware::using('token-policies.create'))->name('store');
            Route::delete('/token-policies', 'bulkDestroy')->middleware(PermissionMiddleware::using('token-policies.deleteAny'))->name('bulk-destroy');
            Route::get('/token-policies/{tokenPolicy}/edit', 'edit')->middleware(PermissionMiddleware::using('token-policies.update'))->name('edit');
            Route::put('/token-policies/{tokenPolicy}', 'update')->middleware(PermissionMiddleware::using('token-policies.update'))->name('update');
            Route::delete('/token-policies/{tokenPolicy}', 'destroy')->middleware(PermissionMiddleware::using('token-policies.delete'))->name('destroy');
        });

        // PlaceholderPageController routes
        Route::controller(PlaceholderPageController::class)->group(function () {
            Route::get('/audit-logs', 'auditLogs')->middleware(PermissionMiddleware::using('audit-logs.viewAny'))->name('audit-logs.index');
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
