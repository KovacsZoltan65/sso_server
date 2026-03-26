<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sso_client_id')->constrained('sso_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_policy_id')->nullable()->constrained('token_policies')->nullOnDelete();
            $table->foreignId('authorization_code_id')->nullable()->constrained('authorization_codes')->nullOnDelete();
            $table->foreignId('parent_token_id')->nullable()->constrained('tokens')->nullOnDelete();
            $table->char('access_token_hash', 64)->unique();
            $table->char('refresh_token_hash', 64)->nullable()->unique();
            $table->json('scopes')->nullable();
            $table->timestamp('access_token_expires_at');
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('access_token_revoked_at')->nullable();
            $table->timestamp('refresh_token_revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('issued_from_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['sso_client_id', 'user_id']);
            $table->index(['sso_client_id', 'access_token_expires_at']);
            $table->index(['sso_client_id', 'refresh_token_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
