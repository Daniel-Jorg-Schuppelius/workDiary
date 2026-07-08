<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_13_100100_create_scim_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer-Token je Organisation für den SCIM-2.0-Provisioning-Endpunkt
 * (Feature 057, MVP-121). Es wird ausschließlich der SHA-256-Hash gespeichert
 * (Klartext nur einmal bei Ausstellung) — Muster wie `location_device_tokens`.
 * Widerruf über `revoked_at`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('scim_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'scimtok_org_fk')->cascadeOnDelete();
            $table->string('label');
            $table->string('token_hash', 64)->unique('scimtok_hash_unique');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'scimtok_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'scimtok_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('scim_tokens');
    }
};
