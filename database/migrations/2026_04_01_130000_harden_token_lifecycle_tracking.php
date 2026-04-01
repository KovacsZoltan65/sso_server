<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokens', function (Blueprint $table): void {
            $table->uuid('family_id')->nullable()->after('parent_token_id')->index();
            $table->foreignId('replaced_by_token_id')->nullable()->after('family_id')->constrained('tokens')->nullOnDelete();
            $table->timestamp('refresh_token_used_at')->nullable()->after('refresh_token_revoked_at');
            $table->timestamp('refresh_token_reuse_detected_at')->nullable()->after('refresh_token_used_at');
            $table->string('access_token_revoked_reason', 100)->nullable()->after('refresh_token_reuse_detected_at');
            $table->string('refresh_token_revoked_reason', 100)->nullable()->after('access_token_revoked_reason');
            $table->json('meta')->nullable()->after('refresh_token_revoked_reason');

            $table->index(['family_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replaced_by_token_id');
            $table->dropIndex(['family_id', 'created_at']);
            $table->dropColumn([
                'family_id',
                'refresh_token_used_at',
                'refresh_token_reuse_detected_at',
                'access_token_revoked_reason',
                'refresh_token_revoked_reason',
                'meta',
            ]);
        });
    }
};
