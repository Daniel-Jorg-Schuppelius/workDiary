<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_26_100000_add_jit_provisioning_to_sso_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JIT-Provisioning je SSO-Verbindung (Feature 057-Ausbau, MS365-Plan G2):
 * Opt-in „Nutzer beim ersten IdP-Login anlegen" mit fester Standardrolle.
 * Default aus — das bisherige Verhalten (unknown_identity ⇒ Ablehnung,
 * Provisioning nur manuell/SCIM) bleibt unverändert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->boolean('jit_provisioning')->default(false)->after('allow_email_link');
            $table->string('jit_role', 64)->nullable()->after('jit_provisioning');
        });
    }

    public function down(): void {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->dropColumn(['jit_provisioning', 'jit_role']);
        });
    }
};
