<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminNavigation;
use App\Support\AuditLogPage;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderPageController extends Controller
{
    /**
     * @return \Inertia\Response
     */
    public function roles(): Response
    {
        return $this->render('roles');
    }

    /**
     * @return \Inertia\Response
     */
    public function permissions(): Response
    {
        return $this->render('permissions');
    }

    /**
     * @return \Inertia\Response
     */
    public function clients(): Response
    {
        return $this->render('sso-clients');
    }

    /**
     * @return \Inertia\Response
     */
    public function scopes(): Response
    {
        return $this->render('scopes');
    }

    /**
     * @return \Inertia\Response
     */
    public function tokenPolicies(): Response
    {
        return $this->render('token-policies');
    }

    /**
     * @return \Inertia\Response
     */
    public function auditLogs(): Response
    {
        $this->authorize('viewAny', AuditLogPage::class);

        return $this->render('audit-logs');
    }

    /**
     * @param string $key
     * @return \Inertia\Response
     */
    private function render(string $key): Response
    {
        return Inertia::render('Admin/PlaceholderPage', [
            'page' => AdminNavigation::find($key),
        ]);
    }
}
