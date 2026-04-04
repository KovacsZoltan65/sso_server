<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_client_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('sso_clients')->cascadeOnDelete();
            $table->json('granted_scope_codes');
            $table->string('granted_scope_fingerprint', 64);
            $table->string('redirect_uri_hash', 64);
            $table->string('trust_tier_snapshot', 64);
            $table->boolean('consent_bypass_allowed_snapshot')->default(false);
            $table->string('consent_policy_version', 64);
            $table->timestamp('granted_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'client_id', 'granted_scope_fingerprint'], 'ucc_lookup_idx');
            $table->index(['user_id', 'client_id', 'granted_scope_fingerprint', 'revoked_at'], 'ucc_active_idx');
            $table->index('expires_at');
            $table->index('redirect_uri_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_client_consents');
    }
};
