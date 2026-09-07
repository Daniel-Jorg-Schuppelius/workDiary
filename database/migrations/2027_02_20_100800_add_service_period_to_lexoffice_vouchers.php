<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100800_add_service_period_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152: Leistungszeitraum der Lexoffice-Rechnung (shippingConditions:
 * Leistungs-/Lieferdatum bzw. -zeitraum). Das Reselling-Register ordnet
 * Positionen damit der Periode zu, die der Zeitraum trifft — statt über das
 * Rechnungsdatum zu raten. Kommt mit dem Positionsspiegel
 * (`lexoffice:sync-voucher-lines --refresh` lädt Bestandsrechnungen nach).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->date('service_starts_on')->nullable()->after('recipient_name');
            $t->date('service_ends_on')->nullable()->after('service_starts_on');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropColumn(['service_starts_on', 'service_ends_on']);
        });
    }
};
