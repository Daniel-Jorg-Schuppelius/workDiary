<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_27_100000_add_is_direct_billing_to_resale_periods.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lizenzjahr vor dem Beginn des Portalkontos, das der Reseller noch selbst berechnet (MVP-1747): Rechnungsvorschlag trotz Portalabrechnung der Domain. */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('resale_periods', 'is_direct_billing')) {
            return;
        }
        Schema::table('resale_periods', static function (Blueprint $t): void {
            $t->boolean('is_direct_billing')->default(false);
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('resale_periods', 'is_direct_billing')) {
            return;
        }
        Schema::table('resale_periods', static function (Blueprint $t): void {
            $t->dropColumn('is_direct_billing');
        });
    }
};
