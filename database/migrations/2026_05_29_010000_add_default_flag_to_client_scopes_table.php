<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_scopes', 'is_default')) {
            Schema::table('client_scopes', function (Blueprint $table): void {
                $table->boolean('is_default')->default(false)->after('scope_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_scopes', 'is_default')) {
            Schema::table('client_scopes', function (Blueprint $table): void {
                $table->dropColumn('is_default');
            });
        }
    }
};
