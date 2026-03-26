<?php

namespace App\Providers;

use App\Models\SsoClient;
use App\Models\Scope;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\ScopePolicy;
use App\Policies\TokenPolicyPolicy;
use App\Policies\UserPolicy;
use App\Repositories\ClientRepository;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\ScopeRepositoryInterface;
use App\Repositories\Contracts\TokenPolicyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\ScopeRepository;
use App\Repositories\TokenPolicyRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Repositories\Contracts\TokenRepositoryInterface;
use App\Repositories\TokenRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(ScopeRepositoryInterface::class, ScopeRepository::class);
        $this->app->bind(TokenPolicyRepositoryInterface::class, TokenPolicyRepository::class);
        $this->app->bind(TokenRepositoryInterface::class, TokenRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SsoClient::class, ClientPolicy::class);
        Gate::policy(Scope::class, ScopePolicy::class);
        Gate::policy(TokenPolicy::class, TokenPolicyPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
