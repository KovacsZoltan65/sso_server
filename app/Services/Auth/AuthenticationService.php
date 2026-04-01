<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): void
    {
        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey());

            if (RateLimiter::tooManyAttempts($request->throttleKey(), 5)) {
                event(new Lockout($request));

                $this->auditLogService->logFailure(
                    logName: AuditLogService::LOG_AUTH,
                    event: 'auth.lockout.triggered',
                    description: 'User login rate limited.',
                    properties: [
                        'reason' => 'rate_limited',
                        ...$this->auditLogService->requestContext($request),
                    ],
                );

                $seconds = RateLimiter::availableIn($request->throttleKey());

                throw ValidationException::withMessages([
                    'email' => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                ]);
            }

            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_AUTH,
                event: 'auth.login.failed',
                description: 'User login failed.',
                properties: [
                    'reason' => 'invalid_credentials',
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        /** @var User|null $user */
        $user = $request->user();

        if ($user instanceof User) {
            if (! $user->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $this->auditLogService->logFailure(
                    logName: AuditLogService::LOG_AUTH,
                    event: 'auth.login.failed',
                    description: 'User login failed.',
                    subject: $user,
                    causer: $user,
                    properties: [
                        'reason' => 'inactive_user',
                        ...$this->auditLogService->requestContext($request),
                    ],
                );

                throw ValidationException::withMessages([
                    'email' => 'Your account is inactive.',
                ]);
            }

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_AUTH,
                event: 'auth.login.succeeded',
                description: 'User logged in.',
                subject: $user,
                causer: $user,
                properties: $this->auditLogService->requestContext($request),
            );
        }
    }

    public function logout(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user instanceof User) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_AUTH,
                event: 'auth.logout.succeeded',
                description: 'User logged out.',
                subject: $user,
                causer: $user,
                properties: $this->auditLogService->requestContext($request),
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($request->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($request));

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_AUTH,
            event: 'auth.lockout.triggered',
            description: 'User login rate limited.',
            properties: [
                'reason' => 'rate_limited',
                ...$this->auditLogService->requestContext($request),
            ],
        );

        $seconds = RateLimiter::availableIn($request->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
