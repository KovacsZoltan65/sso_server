<?php

namespace Database\Seeders;

use App\Models\Scope;
use Illuminate\Database\Seeder;

class ScopeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $scope) {
            Scope::query()->updateOrCreate(
                ['code' => $scope['code']],
                $scope,
            );
        }
    }

    /**
     * @return array<int, array{name: string, code: string, description: string, is_active: bool}>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'OpenID',
                'code' => 'openid',
                'description' => 'Authenticate the subject and issue an ID token.',
                'is_active' => true,
            ],
            [
                'name' => 'Profile',
                'code' => 'profile',
                'description' => 'Access standard profile claims for the subject.',
                'is_active' => true,
            ],
            [
                'name' => 'Email',
                'code' => 'email',
                'description' => 'Access verified email claims for the subject.',
                'is_active' => true,
            ],
            [
                'name' => 'Offline Access',
                'code' => 'offline_access',
                'description' => 'Allow refresh-token based session continuation.',
                'is_active' => true,
            ],
            [
                'name' => 'Users Read',
                'code' => 'users.read',
                'description' => 'Read user directory details from downstream applications.',
                'is_active' => true,
            ],
            [
                'name' => 'Users Manage',
                'code' => 'users.manage',
                'description' => 'Manage user lifecycle from trusted client applications.',
                'is_active' => true,
            ],
            [
                'name' => 'Clients Manage',
                'code' => 'clients.manage',
                'description' => 'Manage client registrations through privileged integrations.',
                'is_active' => true,
            ],
            [
                'name' => 'Tokens Issue',
                'code' => 'tokens.issue',
                'description' => 'Issue access tokens for trusted machine-to-machine flows.',
                'is_active' => true,
            ],
        ];
    }
}
