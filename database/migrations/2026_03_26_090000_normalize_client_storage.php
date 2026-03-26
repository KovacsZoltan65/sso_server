<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirect_uris', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sso_client_id')->constrained()->cascadeOnDelete();
            $table->text('uri');
            $table->char('uri_hash', 64);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['sso_client_id', 'uri_hash']);
            $table->index('sso_client_id');
        });

        Schema::create('client_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sso_client_id')->constrained('sso_clients')->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sso_client_id', 'scope_id']);
        });

        Schema::create('client_secrets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sso_client_id')->constrained('sso_clients')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('secret_hash');
            $table->string('last_four', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['sso_client_id', 'is_active']);
        });

        $clients = DB::table('sso_clients')->select(['id', 'redirect_uris', 'scopes', 'client_secret_hash', 'created_at', 'updated_at'])->get();

        $scopeMap = DB::table('scopes')->pluck('id', 'code');

        foreach ($clients as $client) {
            $createdAt = $client->created_at ?? now();
            $updatedAt = $client->updated_at ?? now();

            $redirectUris = json_decode((string) ($client->redirect_uris ?? '[]'), true);
            if (is_array($redirectUris)) {
                $seenUris = [];
                foreach (array_values($redirectUris) as $index => $uri) {
                    $normalizedUri = trim((string) $uri);
                    if ($normalizedUri === '' || isset($seenUris[$normalizedUri])) {
                        continue;
                    }

                    $seenUris[$normalizedUri] = true;

                    DB::table('redirect_uris')->insert([
                        'sso_client_id' => $client->id,
                        'uri' => $normalizedUri,
                        'is_primary' => $index === 0,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]);
                }
            }

            $scopeCodes = json_decode((string) ($client->scopes ?? '[]'), true);
            if (is_array($scopeCodes)) {
                $seenScopes = [];
                foreach ($scopeCodes as $scopeCode) {
                    $normalizedScopeCode = trim((string) $scopeCode);
                    $scopeId = $scopeMap[$normalizedScopeCode] ?? null;
                    if ($normalizedScopeCode === '' || $scopeId === null || isset($seenScopes[$scopeId])) {
                        continue;
                    }

                    $seenScopes[$scopeId] = true;

                    DB::table('client_scopes')->insert([
                        'sso_client_id' => $client->id,
                        'scope_id' => $scopeId,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]);
                }
            }

            $secretHash = trim((string) ($client->client_secret_hash ?? ''));
            if ($secretHash !== '') {
                DB::table('client_secrets')->insert([
                    'sso_client_id' => $client->id,
                    'name' => 'Migrated initial secret',
                    'secret_hash' => $secretHash,
                    'last_four' => null,
                    'is_active' => true,
                    'revoked_at' => null,
                    'expires_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_secrets');
        Schema::dropIfExists('client_scopes');
        Schema::dropIfExists('redirect_uris');
    }
};
