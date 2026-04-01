<?php

namespace App\Providers;
use App\Models\AuditLog;
use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\Scope;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ClientUserAccessPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\ScopePolicy;
use App\Policies\TokenModelPolicy;
use App\Policies\TokenPolicyPolicy;
use App\Policies\UserPolicy;
use App\Repositories\ClientUserAccessRepository;
use App\Repositories\ClientRepository;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ClientUserAccessRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\ScopeRepositoryInterface;
use App\Repositories\Contracts\TokenPolicyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\ScopeRepository;
use App\Repositories\TokenPolicyRepository;
use App\Repositories\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use App\Support\AuditLogPage;
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
        $this->app->bind(ClientUserAccessRepositoryInterface::class, ClientUserAccessRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
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
        Gate::policy(ClientUserAccess::class, ClientUserAccessPolicy::class);
        Gate::policy(Scope::class, ScopePolicy::class);
        Gate::policy(Token::class, TokenModelPolicy::class);
        Gate::policy(TokenPolicy::class, TokenPolicyPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AuditLogPage::class, AuditLogPolicy::class);

        $this->configureRateLimiting();

        Vite::prefetch(concurrency: 3);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('oauth-token', function (Request $request): array {
            return [
                Limit::perMinute(15)
                    ->by('oauth-token:ip:'.$request->ip()),
                Limit::perMinute(10)
                    ->by('oauth-token:client:'.$this->clientAwareFingerprint($request)),
            ];
        });

        RateLimiter::for('oauth-client', function (Request $request): array {
            return [
                Limit::perMinute(60)
                    ->by('oauth-client:ip:'.$request->ip()),
                Limit::perMinute(30)
                    ->by('oauth-client:client:'.$this->clientAwareFingerprint($request)),
            ];
        });

        RateLimiter::for('oauth-userinfo', function (Request $request): Limit {
            return Limit::perMinute(120)
                ->by('oauth-userinfo:'.$request->ip());
        });
    }

    private function clientAwareFingerprint(Request $request): string
    {
        $clientId = trim((string) $request->input('client_id', 'guest'));

        return $clientId.'|'.$request->ip();
    }
}
