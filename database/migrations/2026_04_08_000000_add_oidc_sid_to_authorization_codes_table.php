<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table): void {
            $table->string('oidc_sid', 128)->nullable()->after('nonce');
            $table->index(['sso_client_id', 'oidc_sid']);
        });
    }

    public function down(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table): void {
            $table->dropIndex(['sso_client_id', 'oidc_sid']);
            $table->dropColumn('oidc_sid');
        });
    }
};
