<?php

use App\Models\SsoClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->string('trust_tier')
                ->default(SsoClient::TRUST_TIER_THIRD_PARTY)
                ->after('token_policy_id');
            $table->boolean('is_first_party')
                ->default(false)
                ->after('trust_tier');
            $table->boolean('consent_bypass_allowed')
                ->default(false)
                ->after('is_first_party');
        });
    }

    public function down(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->dropColumn([
                'trust_tier',
                'is_first_party',
                'consent_bypass_allowed',
            ]);
        });
    }
};
