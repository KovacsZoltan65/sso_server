<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sso_client_id')->constrained('sso_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_policy_id')->nullable()->constrained('token_policies')->nullOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->char('redirect_uri_hash', 64)->index();
            $table->string('code_challenge', 255)->nullable();
            $table->string('code_challenge_method', 10)->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['sso_client_id', 'user_id']);
            $table->index(['sso_client_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_codes');
    }
};
