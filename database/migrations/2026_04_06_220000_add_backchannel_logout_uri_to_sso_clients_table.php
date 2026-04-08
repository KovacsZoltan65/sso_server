<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->string('backchannel_logout_uri', 2048)
                ->nullable()
                ->after('frontchannel_logout_uri');
        });
    }

    public function down(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->dropColumn('backchannel_logout_uri');
        });
    }
};
