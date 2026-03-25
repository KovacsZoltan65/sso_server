<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlaceholderPageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {

        // UserController routes
        Route::controller(UserController::class)->name('users.')->group(function () {
            Route::get('/users', 'index')->middleware(PermissionMiddleware::using('users.view'))->name('index');
            Route::post('/users', 'store')->middleware(PermissionMiddleware::using('users.manage'))->name('store');
            Route::delete('/users', 'bulkDestroy')->middleware(PermissionMiddleware::using('users.manage'))->name('bulk-destroy');
            Route::put('/users/{user}', 'update')->middleware(PermissionMiddleware::using('users.manage'))->name('update');
            Route::delete('/users/{user}', 'destroy')->middleware(PermissionMiddleware::using('users.manage'))->name('destroy');
        });

        // RoleController routes
        Route::controller(RoleController::class)->name('roles.')->group(function () {
            Route::get('/roles', 'index')->middleware(PermissionMiddleware::using('roles.view'))->name('index');
            Route::get('/roles/create', 'create')->middleware(PermissionMiddleware::using('roles.manage'))->name('create');
            Route::post('/roles', 'store')->middleware(PermissionMiddleware::using('roles.manage'))->name('store');
            Route::delete('/roles', 'bulkDestroy')->middleware(PermissionMiddleware::using('roles.manage'))->name('bulk-destroy');
            Route::get('/roles/{role}/edit', 'edit')->middleware(PermissionMiddleware::using('roles.manage'))->name('edit');
            Route::put('/roles/{role}', 'update')->middleware(PermissionMiddleware::using('roles.manage'))->name('update');
            Route::delete('/roles/{role}', 'destroy')->middleware(PermissionMiddleware::using('roles.manage'))->name('destroy');
        });

        // PermissionController routes
        Route::controller(PermissionController::class)->name('permissions.')->group(function () {
            Route::get('/permissions', 'index')->middleware(PermissionMiddleware::using('permissions.view'))->name('index');
            Route::get('/permissions/create', 'create')->middleware(PermissionMiddleware::using('permissions.manage'))->name('create');
            Route::post('/permissions', 'store')->middleware(PermissionMiddleware::using('permissions.manage'))->name('store');
            Route::delete('/permissions', 'bulkDestroy')->middleware(PermissionMiddleware::using('permissions.manage'))->name('bulk-destroy');
            Route::get('/permissions/{permission}/edit', 'edit')->middleware(PermissionMiddleware::using('permissions.manage'))->name('edit');
            Route::put('/permissions/{permission}', 'update')->middleware(PermissionMiddleware::using('permissions.manage'))->name('update');
            Route::delete('/permissions/{permission}', 'destroy')->middleware(PermissionMiddleware::using('permissions.manage'))->name('destroy');
        });

        // ClientController routes
        Route::controller(ClientController::class)->name('sso-clients.')->group(function () {
            Route::get('/sso-clients', 'index')->middleware(PermissionMiddleware::using('sso-clients.view'))->name('index');
            Route::get('/sso-clients/create', 'create')->middleware(PermissionMiddleware::using('sso-clients.manage'))->name('create');
            Route::post('/sso-clients', 'store')->middleware(PermissionMiddleware::using('sso-clients.manage'))->name('store');
            Route::get('/sso-clients/{ssoClient}/edit', 'edit')->middleware(PermissionMiddleware::using('sso-clients.manage'))->name('edit');
            Route::put('/sso-clients/{ssoClient}', 'update')->middleware(PermissionMiddleware::using('sso-clients.manage'))->name('update');
            Route::delete('/sso-clients/{ssoClient}', 'destroy')->middleware(PermissionMiddleware::using('sso-clients.manage'))->name('destroy');
        });

        // PlaceholderPageController routes
        Route::controller(PlaceholderPageController::class)->group(function () {
            Route::get('/scopes', 'scopes')->middleware(PermissionMiddleware::using('scopes.view'))->name('scopes.index');
            Route::get('/token-policies', 'tokenPolicies')->middleware(PermissionMiddleware::using('token-policies.view'))->name('token-policies.index');
            Route::get('/audit-logs', 'auditLogs')->middleware(PermissionMiddleware::using('audit-logs.view'))->name('audit-logs.index');
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
