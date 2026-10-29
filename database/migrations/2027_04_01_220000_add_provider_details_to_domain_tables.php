<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_220000_add_provider_details_to_domain_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Vollständigkeit des Live-Vertrags (domain:contract 2026-10-29, Feature 083): alles, was der
// Anbieter liefert, bekommt einen Platz — außer Passwörtern und Auth-Codes. Domains: Bezahlt-bis,
// nächste Aktion mit Datum, Laufzeit, Anbieter-Änderungsstand und technische Angaben (Nameserver,
// Kontakt-Handles, DNSSEC, Whois-Privacy) als JSON; Subuser: E-Mail, Kredit, MwSt-Satz, Notiz,
// Anlage und letzte Anmeldung beim Anbieter; Ereignisse: Infotext aus StatusEvent.
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_projections', function (Blueprint $t): void {
            $t->date('paid_until_at')->nullable()->after('accounting_at');
            $t->date('next_action_at')->nullable()->after('next_action');
            $t->timestamp('provider_updated_at')->nullable()->after('revision');
            $t->unsignedSmallInteger('renewal_period')->nullable()->after('renewal_currency');
            $t->json('details')->nullable()->after('provider_updated_at');
        });
        Schema::table('domain_reseller_accounts', function (Blueprint $t): void {
            $t->string('email', 190)->nullable()->after('provider_customer_number');
            $t->decimal('credit_snapshot', 15, 2)->nullable()->after('balance_snapshot');
            $t->decimal('vat_rate', 6, 3)->nullable()->after('credit_snapshot');
            $t->string('note', 512)->nullable()->after('email');
            $t->timestamp('provider_created_at')->nullable()->after('note');
            $t->timestamp('last_login_at')->nullable()->after('provider_created_at');
        });
        Schema::table('domain_events', function (Blueprint $t): void {
            $t->string('info', 512)->nullable()->after('object');
        });
    }

    public function down(): void {
        Schema::table('domain_events', function (Blueprint $t): void {
            $t->dropColumn('info');
        });
        Schema::table('domain_reseller_accounts', function (Blueprint $t): void {
            $t->dropColumn(['email', 'credit_snapshot', 'vat_rate', 'note', 'provider_created_at', 'last_login_at']);
        });
        Schema::table('domain_projections', function (Blueprint $t): void {
            $t->dropColumn(['paid_until_at', 'next_action_at', 'provider_updated_at', 'renewal_period', 'details']);
        });
    }
};
