<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminNavigation;
use App\Support\AuditLogPage;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderPageController extends Controller
{
    public function roles(): Response
    {
        return $this->render('roles');
    }

    public function permissions(): Response
    {
        return $this->render('permissions');
    }

    public function clients(): Response
    {
        return $this->render('sso-clients');
    }

    public function scopes(): Response
    {
        return $this->render('scopes');
    }

    public function tokenPolicies(): Response
    {
        return $this->render('token-policies');
    }

    public function auditLogs(): Response
    {
        $this->authorize('viewAny', AuditLogPage::class);

        return $this->render('audit-logs');
    }

    private function render(string $key): Response
    {
        return Inertia::render('Admin/PlaceholderPage', [
            'page' => AdminNavigation::find($key),
        ]);
    }
}
