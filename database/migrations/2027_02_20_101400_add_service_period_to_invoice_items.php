<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101400_add_service_period_to_invoice_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (Review 2026-09-11): Leistungszeitraum je Rechnungsposition —
 * der lokale Rechnungsentwurf schreibt Beginn/Ende der Abo-Periode, der
 * Belegspiegel liest daraus Lizenzmonate. `service_date` bleibt das
 * Leistungsdatum (§ 14 UStG) und der Fallback für Positionen ohne Zeitraum.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $t): void {
            $t->date('service_from')->nullable()->after('service_date');
            $t->date('service_to')->nullable()->after('service_from');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $t): void {
            $t->dropColumn(['service_from', 'service_to']);
        });
    }
};
