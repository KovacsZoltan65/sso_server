<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopes');
    }
};
