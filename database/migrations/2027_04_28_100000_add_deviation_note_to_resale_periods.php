<?php
/*
 * Created on   : Fri Nov 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_28_100000_add_deviation_note_to_resale_periods.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Begründung einer Periode, deren Bezüge in Artikel oder Betrag vom Abo abweichen (MVP-1768): begründet zählt sie nicht mehr als Prüffall. */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('resale_periods', 'deviation_note')) {
            return;
        }
        Schema::table('resale_periods', static function (Blueprint $t): void {
            $t->string('deviation_note')->nullable();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('resale_periods', 'deviation_note')) {
            return;
        }
        Schema::table('resale_periods', static function (Blueprint $t): void {
            $t->dropColumn('deviation_note');
        });
    }
};
