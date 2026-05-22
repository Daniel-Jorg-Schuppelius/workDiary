<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120102_create_per_diem_days_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-Diem-Tag:
 *
 *  - Ein Tag eines Trips mit kind (departure/full/return/single).
 *  - base_amount aus Rate-Tabelle (full oder partial), nach Kürzung (Übernachtung,
 *    Mahlzeiten) folgt amount = base_amount - deductions_total.
 *  - meal_* sind boolesche Flags (gestellte Mahlzeiten) → erzwingen Kürzungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('per_diem_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('per_diem_trip_id')
                ->constrained('per_diem_trips')->cascadeOnDelete();

            $table->date('date');
            $table->string('kind', 20);
            $table->string('country', 2)->default('DE');
            $table->foreignId('per_diem_rate_id')->nullable()
                ->constrained('per_diem_rates')->nullOnDelete();

            $table->decimal('base_amount', 8, 2)->default('0.00');
            $table->decimal('deduction_breakfast', 8, 2)->default('0.00');
            $table->decimal('deduction_lunch', 8, 2)->default('0.00');
            $table->decimal('deduction_dinner', 8, 2)->default('0.00');
            $table->decimal('deductions_total', 8, 2)->default('0.00');
            $table->decimal('amount', 8, 2)->default('0.00');

            $table->boolean('meal_breakfast')->default(false);
            $table->boolean('meal_lunch')->default(false);
            $table->boolean('meal_dinner')->default(false);

            $table->string('currency', 3)->default('EUR');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['per_diem_trip_id', 'date']);
            $table->index(['date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('per_diem_days');
    }
};
