<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_user_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('sso_clients')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('allowed_from')->nullable();
            $table->timestamp('allowed_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('user_id');
            $table->index(['client_id', 'user_id']);
            $table->unique(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user_access');
    }
};
