<?php

use App\Models\SsoClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->string('client_type', 32)
                ->default(SsoClient::CLIENT_TYPE_CONFIDENTIAL)
                ->after('client_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('sso_clients', function (Blueprint $table): void {
            $table->dropIndex(['client_type']);
            $table->dropColumn('client_type');
        });
    }
};
