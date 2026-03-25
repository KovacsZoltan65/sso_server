<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlaceholderPageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');

        Route::delete('/users', [UserController::class, 'bulkDestroy'])
            ->middleware('permission:users.manage')
            ->name('users.bulk-destroy');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.manage')
            ->name('users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('roles.index');

        Route::get('/roles/create', [RoleController::class, 'create'])
            ->middleware('permission:roles.manage')
            ->name('roles.create');

        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.manage')
            ->name('roles.store');

        Route::delete('/roles', [RoleController::class, 'bulkDestroy'])
            ->middleware('permission:roles.manage')
            ->name('roles.bulk-destroy');

        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:roles.manage')
            ->name('roles.edit');

        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.manage')
            ->name('roles.update');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.manage')
            ->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permissions.view')
            ->name('permissions.index');

        Route::get('/permissions/create', [PermissionController::class, 'create'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.create');

        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.store');

        Route::delete('/permissions', [PermissionController::class, 'bulkDestroy'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.bulk-destroy');

        Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.edit');

        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.update');

        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.destroy');

        Route::get('/sso-clients', [ClientController::class, 'index'])
            ->middleware('permission:sso-clients.view')
            ->name('sso-clients.index');

        Route::get('/sso-clients/create', [ClientController::class, 'create'])
            ->middleware('permission:sso-clients.manage')
            ->name('sso-clients.create');

        Route::post('/sso-clients', [ClientController::class, 'store'])
            ->middleware('permission:sso-clients.manage')
            ->name('sso-clients.store');

        Route::get('/sso-clients/{ssoClient}/edit', [ClientController::class, 'edit'])
            ->middleware('permission:sso-clients.manage')
            ->name('sso-clients.edit');

        Route::put('/sso-clients/{ssoClient}', [ClientController::class, 'update'])
            ->middleware('permission:sso-clients.manage')
            ->name('sso-clients.update');

        Route::delete('/sso-clients/{ssoClient}', [ClientController::class, 'destroy'])
            ->middleware('permission:sso-clients.manage')
            ->name('sso-clients.destroy');

        Route::get('/scopes', [PlaceholderPageController::class, 'scopes'])
            ->middleware('permission:scopes.view')
            ->name('scopes.index');

        Route::get('/token-policies', [PlaceholderPageController::class, 'tokenPolicies'])
            ->middleware('permission:token-policies.view')
            ->name('token-policies.index');

        Route::get('/audit-logs', [PlaceholderPageController::class, 'auditLogs'])
            ->middleware('permission:audit-logs.view')
            ->name('audit-logs.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
