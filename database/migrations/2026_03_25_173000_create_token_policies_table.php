<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 150)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('access_token_ttl_minutes');
            $table->unsignedInteger('refresh_token_ttl_minutes');
            $table->boolean('refresh_token_rotation_enabled')->default(true);
            $table->boolean('pkce_required')->default(false);
            $table->boolean('reuse_refresh_token_forbidden')->default(true);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_policies');
    }
};
