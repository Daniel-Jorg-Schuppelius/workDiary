<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_100000_create_jtl_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JTL-Wawi-Verbindung je Organisation (Feature 078, MVP-317): genau EINE
 * Verbindung je Org (unique), beide Betriebsarten in einer Zeile —
 * OnPremise (App-Registrierung → permanenter API-Key) und Cloud-Gateway
 * (OAuth2 Client-Credentials → kurzlebiger Bearer-Token). Alle Secrets
 * (API-Key, Challenge-Code, Client-Credentials, Token) verschlüsselt
 * at-rest (encrypted-Cast, APP_KEY). `last_error` trägt nur die gekürzte
 * Fehlerklasse — nie Payload oder Secrets. Kurze, explizite Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('jtl_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('jtlc_org_unique')->constrained('organizations', indexName: 'jtlc_org_fk')->cascadeOnDelete();
            $table->string('mode', 16)->default('on_premise'); // on_premise / cloud
            $table->string('base_url')->nullable();            // OnPremise: http(s)://host:port/api/eazybusiness — Cloud nutzt das Gateway aus der Plugin-Config
            $table->string('api_version', 8)->default('2.0');
            $table->boolean('allow_private_network')->default(false); // bewusste, auditierte Freigabe privater Adressen (OnPremise-Wawi im LAN)
            $table->string('tenant_id', 64)->nullable();
            $table->string('company_id', 64)->nullable();      // x-companyid (Mandant/Firma innerhalb der Wawi)
            $table->string('app_id', 64)->nullable();
            $table->text('challenge_code')->nullable();        // encrypted-Cast; muss bei allen Registrierungs-Requests identisch sein
            $table->string('registration_id', 128)->nullable();
            $table->string('registration_status', 24)->nullable(); // pending / rejected / accepted
            $table->text('api_key')->nullable();               // encrypted-Cast (von JTL nur einmal ausgegeben)
            $table->text('client_id')->nullable();             // encrypted-Cast (Cloud)
            $table->text('client_secret')->nullable();         // encrypted-Cast (Cloud)
            $table->text('access_token')->nullable();          // encrypted-Cast (Cloud, ~24 h)
            $table->timestamp('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->string('status', 24)->default('draft');    // draft / pending_registration / active / blocked / disconnected
            $table->string('blocked_reason', 64)->nullable();  // missing_scopes / license / contract_deviation
            $table->timestamp('stock_checkpoint_at')->nullable();
            $table->timestamp('article_checkpoint_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->string('last_error', 191)->nullable();     // gekürzte Fehlerklasse, nie Payload/Secrets
            $table->string('detected_version', 64)->nullable(); // aus GET /v2/info
            $table->json('contract_notes')->nullable();        // Abweichungsregister: erkannte API-Vertragsabweichungen
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'jtlc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('jtl_connections');
    }
};
