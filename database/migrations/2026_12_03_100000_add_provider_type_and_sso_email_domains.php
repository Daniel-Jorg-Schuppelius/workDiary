<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_03_100000_add_provider_type_and_sso_email_domains.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 057-Ausbau: Anbieter-Presets (Microsoft 365 / Google Workspace) und
 * E-Mail-Domain-Discovery für den SSO-Login.
 *
 * - `provider_type` unterscheidet Preset-Anbieter; die Eindeutigkeit je Org
 *   wird von (org, protocol) auf (org, protocol, provider_type) gelockert,
 *   sodass eine Organisation Microsoft UND Google (beide OIDC) parallel
 *   betreiben kann.
 * - `organization_sso_domains` bildet verifizierte E-Mail-Domains auf die
 *   Organisation ab (global eindeutig), damit die Login-Discovery die
 *   passende SSO-Organisation aus der E-Mail-Adresse bestimmen kann.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('sso_connections', 'provider_type')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                // custom|microsoft|google (App\Enums\Auth\SsoProviderType)
                $table->string('provider_type', 16)->default('custom')->after('protocol');
            });
        }

        // Neue, FK-taugliche Unique-Constraint ZUERST anlegen (organization_id
        // bleibt führend), erst dann die alte entfernen — sonst verweigert
        // MySQL das Droppen des Index, der die Fremdschlüssel-Spalte stützt
        // (Fehler 1553). Existenz-Guards machen die Migration wiederholbar.
        if (! Schema::hasIndex('sso_connections', 'sso_conn_org_protocol_provider_unique')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                $table->unique(['organization_id', 'protocol', 'provider_type'], 'sso_conn_org_protocol_provider_unique');
            });
        }
        if (Schema::hasIndex('sso_connections', 'sso_conn_org_protocol_unique')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                $table->dropUnique('sso_conn_org_protocol_unique');
            });
        }

        if (! Schema::hasTable('organization_sso_domains')) {
            Schema::create('organization_sso_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('domain', 191); // normalisiert kleingeschrieben, ohne führendes @
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('domain', 'org_sso_domain_unique');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('organization_sso_domains');

        // Erst die alte Unique-Constraint wiederherstellen (stützt den FK),
        // dann die neue entfernen — spiegelbildlich zur FK-Restriktion in up().
        if (! Schema::hasIndex('sso_connections', 'sso_conn_org_protocol_unique')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                $table->unique(['organization_id', 'protocol'], 'sso_conn_org_protocol_unique');
            });
        }
        if (Schema::hasIndex('sso_connections', 'sso_conn_org_protocol_provider_unique')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                $table->dropUnique('sso_conn_org_protocol_provider_unique');
            });
        }

        if (Schema::hasColumn('sso_connections', 'provider_type')) {
            Schema::table('sso_connections', function (Blueprint $table) {
                $table->dropColumn('provider_type');
            });
        }
    }
};
