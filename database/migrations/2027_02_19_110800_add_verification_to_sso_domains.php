<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110800_add_verification_to_sso_domains.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * SSO-Domains müssen nachgewiesen werden (Sicherheitsscan 2026-08-23, S-49).
 *
 * Bisher genügte eine syntaktisch gültige Domain: wer sie zuerst eintrug,
 * bekam sie. `SsoController::discover(?email=)` leitet danach **jeden**, der
 * eine Adresse dieser Domain eingibt, zum IdP dieser Organisation — bei
 * aktivem JIT-Provisioning samt Kontoanlage dort. Ein Mandant konnte damit die
 * Mail-Domain eines anderen beanspruchen oder eine öffentliche Domain
 * blockieren.
 *
 * Nachgewiesen wird über einen DNS-TXT-Eintrag `_workdiary-sso.<domain>` mit
 * dem hier erzeugten Token.
 *
 * **Bestandsdomains gelten als verifiziert.** Sie rückwirkend zu entwerten
 * würde funktionierende Anmeldungen abschalten, ohne dass jemand etwas falsch
 * gemacht hätte — der Nachweis greift ab jetzt für neue Einträge.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('organization_sso_domains')) {
            return;
        }

        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            $table->string('verification_token', 64)->nullable()->after('domain');
            $table->timestamp('verified_at')->nullable()->after('verification_token');
            $table->timestamp('verification_checked_at')->nullable()->after('verified_at');
        });

        // Bestand bleibt gültig — siehe Klassenkommentar.
        DB::table('organization_sso_domains')->update(['verified_at' => now()]);
    }

    public function down(): void {
        if (! Schema::hasTable('organization_sso_domains')) {
            return;
        }

        Schema::table('organization_sso_domains', function (Blueprint $table): void {
            $table->dropColumn(['verification_token', 'verified_at', 'verification_checked_at']);
        });
    }
};
