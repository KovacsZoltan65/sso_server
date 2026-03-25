<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('client_id')->unique();
            $table->text('client_secret_hash');
            $table->json('redirect_uris');
            $table->boolean('is_active')->default(true);
            $table->json('scopes')->nullable();
            $table->unsignedBigInteger('token_policy_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_clients');
    }
};
