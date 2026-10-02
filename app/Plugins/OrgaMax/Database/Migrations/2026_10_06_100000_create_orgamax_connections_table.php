<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_06_100000_create_orgamax_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 29 (Feature 077): orgaMAX-Buchhaltung-Verbindung — genau eine je
 * Organisation. Zwei Betriebsarten: private Pilot-Erweiterung (Key/Secret je
 * Org) und veröffentlichte Erweiterung (Betreibergeheimnis, je Org nur
 * ownershipId/Token). Secrets verschlüsselt at-rest; der iid-Callback bindet
 * nur mit zuvor begonnener, tokenisierter Verbindungsabsicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('orgamax_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 20)->default('private'); // private | marketplace
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('ownership_id')->nullable();
            $table->text('bearer_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->json('account_snapshot')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('blocked_reason')->nullable();
            // Verbindungsabsicht (Anti-Fremd-iid): Hash + Ablauf.
            $table->char('intent_token_hash', 64)->nullable();
            $table->dateTime('intent_expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            // Datenführerschaft je Capability (customers/suppliers/articles/…).
            $table->json('capabilities')->nullable();
            $table->json('checkpoints')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->string('last_error')->nullable();
            $table->json('contract_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('orgamax_connections');
    }
};
