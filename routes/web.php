<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlaceholderPageController;
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

        Route::get('/roles', [PlaceholderPageController::class, 'roles'])
            ->middleware('permission:roles.view')
            ->name('roles.index');

        Route::get('/permissions', [PlaceholderPageController::class, 'permissions'])
            ->middleware('permission:permissions.view')
            ->name('permissions.index');

        Route::get('/sso-clients', [PlaceholderPageController::class, 'clients'])
            ->middleware('permission:sso-clients.view')
            ->name('sso-clients.index');

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
