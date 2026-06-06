<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_12_000000_add_compensation_model_to_users.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vergütungsmodell für (auch externe) Mitarbeiter:
 *  - compensation_model: payroll (intern, dt. Lohn) | pauschal | nach_zeitaufwand
 *  - flat_amount/flat_interval: Pauschale (Festbetrag je Intervall)
 *  - compensation_rate: Stundensatz für zeitbasierte externe Vergütung
 *    (getrennt vom kundenseitigen hourly_rate/internal_rate)
 *
 * NULL bei compensation_model = interner Payroll-Mitarbeiter (Bestandsverhalten).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('compensation_model', 32)->nullable()->after('employment_type');
            $table->decimal('flat_amount', 12, 2)->nullable()->after('compensation_model');
            $table->string('flat_interval', 32)->nullable()->after('flat_amount');
            $table->decimal('compensation_rate', 12, 2)->nullable()->after('flat_interval');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['compensation_model', 'flat_amount', 'flat_interval', 'compensation_rate']);
        });
    }
};
