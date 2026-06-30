<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120400_create_location_device_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-Gerät-Token für den Standort-Ingest (OwnTracks/Traccar senden ohne
 * interaktive Anmeldung). Nur der SHA-256-Hash wird gespeichert; widerrufbar
 * über `revoked_at`, unabhängig von den API-Session-Tokens (Sanctum).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('location_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('label', 120);
            $table->string('token_hash', 64);
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();

            $table->timestamps();

            $table->unique('token_hash', 'ldt_uniq_token_hash');
            $table->index(['organization_id', 'user_id'], 'ldt_idx_org_user');
        });
    }

    public function down(): void {
        Schema::dropIfExists('location_device_tokens');
    }
};
