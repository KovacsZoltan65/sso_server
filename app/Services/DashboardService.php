<?php

namespace App\Services;

use App\Data\DashboardStatData;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [
            'stats' => DashboardStatData::collect([
                new DashboardStatData('Users', (string) User::count(), 'pi pi-users', 'blue'),
                new DashboardStatData('Roles', (string) Role::count(), 'pi pi-id-card', 'slate'),
                new DashboardStatData('Permissions', (string) Permission::count(), 'pi pi-shield', 'amber'),
                new DashboardStatData('Audit Entries', (string) Activity::count(), 'pi pi-history', 'emerald'),
            ]),
            'recentActivity' => Activity::query()
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'logName' => $activity->log_name,
                    'causer' => $activity->causer?->name ?? 'System',
                    'createdAt' => $activity->created_at?->diffForHumans(),
                ])
                ->all(),
            'permissionGroups' => [
                'core' => count(\App\Support\SsoPermissions::grouped()['core']),
                'sso' => count(\App\Support\SsoPermissions::grouped()['sso']),
            ],
        ];
    }
}
