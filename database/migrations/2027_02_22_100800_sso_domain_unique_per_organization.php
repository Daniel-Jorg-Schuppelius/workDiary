<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100800_sso_domain_unique_per_organization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicherheitsaudit 2026-09-17 (tenant-sso-domain-6): Die Eindeutigkeit galt
 * global — ein Mandant sperrte mit einem bloßen, UNVERIFIZIERTEN Eintrag die
 * Domain für alle anderen (Squatting) und erfuhr aus der Fehlermeldung, dass
 * sie anderswo belegt ist.
 *
 * Eindeutig ist die Domain jetzt je Organisation; dass nur EIN Mandant sie
 * nachweisen kann, erzwingt die Anwendung beim Nachweis
 * ({@see \App\Http\Controllers\Admin\SsoAdminController::verifyDomain()}) —
 * MariaDB kennt keine Teilindizes.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('organization_sso_domains')) {
            return;
        }

        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            if (Schema::hasIndex('organization_sso_domains', 'org_sso_domain_org_unique')) {
                return;
            }
            $table->unique(['domain', 'organization_id'], 'org_sso_domain_org_unique');
        });

        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            if (Schema::hasIndex('organization_sso_domains', 'org_sso_domain_unique')) {
                $table->dropUnique('org_sso_domain_unique');
            }
        });
    }

    public function down(): void {
        if (! Schema::hasTable('organization_sso_domains')) {
            return;
        }

        // Zurück auf die globale Eindeutigkeit: doppelte Domains müssten dafür
        // zuvor bereinigt sein — sonst scheitert der Index, und das ist richtig so.
        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            if (! Schema::hasIndex('organization_sso_domains', 'org_sso_domain_unique')) {
                $table->unique('domain', 'org_sso_domain_unique');
            }
        });

        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            if (Schema::hasIndex('organization_sso_domains', 'org_sso_domain_org_unique')) {
                $table->dropUnique('org_sso_domain_org_unique');
            }
        });
    }
};
