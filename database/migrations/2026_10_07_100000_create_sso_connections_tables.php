<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_07_100000_create_sso_connections_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 057 (MVP-120/121): SSO-Anbindungen je Organisation (OIDC + SAML)
 * und die Kontoverknüpfung Identität ↔ WorkDiary-Konto. Account-Linking
 * läuft ausschließlich über (Verbindung, Subject) — der Issuer hängt an der
 * Verbindung, zusammen entspricht das der geforderten iss+sub-Identität.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sso_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('protocol', 8); // oidc|saml (App\Enums\Auth\SsoProtocol)
            $table->string('label', 120);
            $table->boolean('active')->default(false);
            $table->boolean('enforced')->default(false);
            $table->boolean('allow_email_link')->default(false);
            // SSRF-Opt-in für IdPs im privaten Netz (On-Premise), Muster JTL-Plugin.
            $table->boolean('allow_private_network')->default(false);
            // OIDC (MVP-120)
            $table->string('issuer', 500)->nullable();
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable(); // encrypted at-rest, nie ''
            $table->string('scopes', 255)->nullable();
            // SAML (MVP-121)
            $table->string('idp_entity_id', 500)->nullable();
            $table->string('idp_sso_url', 500)->nullable();
            $table->text('idp_certificate')->nullable();
            $table->text('idp_certificate_next')->nullable(); // Zertifikatsrotation (x509certMulti)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'protocol'], 'sso_conn_org_protocol_unique');
        });

        Schema::create('sso_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sso_connection_id')->constrained('sso_connections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 191); // OIDC sub bzw. SAML NameID
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['sso_connection_id', 'subject'], 'sso_ident_conn_subject_unique');
            $table->unique(['sso_connection_id', 'user_id'], 'sso_ident_conn_user_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            // Break-Glass: darf sich trotz SSO-Pflicht weiter lokal anmelden.
            $table->boolean('sso_exempt')->default(false)->after('deactivated_at');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sso_exempt');
        });
        Schema::dropIfExists('sso_identities');
        Schema::dropIfExists('sso_connections');
    }
};
