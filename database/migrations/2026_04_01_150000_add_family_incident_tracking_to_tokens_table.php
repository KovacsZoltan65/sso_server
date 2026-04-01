<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokens', function (Blueprint $table): void {
            $table->timestamp('family_revoked_at')->nullable()->after('refresh_token_reuse_detected_at')->index();
            $table->string('family_revoked_reason', 100)->nullable()->after('family_revoked_at');
            $table->timestamp('security_incident_at')->nullable()->after('family_revoked_reason')->index();
            $table->string('security_incident_reason', 100)->nullable()->after('security_incident_at');
        });
    }

    public function down(): void
    {
        Schema::table('tokens', function (Blueprint $table): void {
            $table->dropColumn([
                'family_revoked_at',
                'family_revoked_reason',
                'security_incident_at',
                'security_incident_reason',
            ]);
        });
    }
};
