<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120100_create_per_diem_rates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verpflegungs-/Übernachtungspauschale nach BMF (Per-Diem Rates):
 *
 *  - Globale Stammdaten (kein organization_id) — BMF-Sätze sind Bundesrecht.
 *  - country (ISO 3166-1 alpha-2), gültig im Zeitraum [valid_from, valid_to).
 *  - full_day_amount: Tagessatz Volltag (Inland DE: 28 EUR ab 2020-01-01)
 *  - partial_day_amount: Anreise-/Abreisetag bzw. Eintägig > 8h (Inland DE: 14 EUR)
 *  - overnight_amount: Übernachtungspauschale ohne Beleg (Inland DE: 20 EUR)
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('per_diem_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('country', 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('full_day_amount', 8, 2);
            $table->decimal('partial_day_amount', 8, 2);
            $table->decimal('overnight_amount', 8, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('source', 100)->nullable();
            $table->timestamps();

            $table->index(['country', 'valid_from']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('per_diem_rates');
    }
};
