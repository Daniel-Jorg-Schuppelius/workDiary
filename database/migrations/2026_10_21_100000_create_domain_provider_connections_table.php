<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_21_100000_create_domain_provider_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DomainReselling-Verbindung (Feature 083, MVP-385). Per Organisation:
 * Umgebung (OT&E/Prod), fester allowlisteter Endpunkt, Login und
 * VERSCHLÜSSELTES Passwort. Login/Passwort erscheinen nie in URLs/Logs.
 * `capabilities` trägt die erkannte Fähigkeitsmatrix; `pilot_confirmed_at`
 * bleibt NULL, solange kein realer OT&E-/Produktivpilot bestanden ist
 * (Adapter sichtbar „Pilot offen").
 *
 * Kurze, DB-weit eindeutige Index-/FK-Präfixe `dpc_` (MySQL-64-Zeichen-Limit,
 * errno 121 bei doppelten FK-Namen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('domain_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('environment', 16)->default('ote'); // ote | production
            $table->string('name', 190);

            // Fester allowlisteter Endpunkt-Schlüssel (kein freies URL-Feld).
            $table->string('endpoint', 190)->default('domainreselling');

            // Zugangsdaten: Login sichtbar, Passwort verschlüsselt (encrypted-Cast).
            $table->string('login', 190);
            $table->text('password')->nullable();
            // Optionaler Standard-`s_user`-Kontext (eigener oder berechtigter Subuser).
            $table->string('default_user', 190)->nullable();

            // Erkannte Fähigkeitsmatrix (DomainCapabilityArea → belegt?).
            $table->json('capabilities')->nullable();

            $table->string('status', 20)->default('draft'); // draft | active | blocked
            $table->timestamp('pilot_confirmed_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();

            // Health-Standard (HasConnectionHealth).
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'environment'], 'dpc_org_env_idx');
            $table->unique(['organization_id', 'endpoint', 'login'], 'dpc_org_endpoint_login_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('domain_provider_connections');
    }
};
