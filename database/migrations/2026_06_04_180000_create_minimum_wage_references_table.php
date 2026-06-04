<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_180000_create_minimum_wage_references_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Globaler (mandantenübergreifender) Referenz-Datenbestand für gesetzliche
 * MONATLICHE Mindestlöhne je Land — Quelle Eurostat (earn_mw_avgr2). Bewusst
 * NICHT organisationsgebunden: es sind länderweite Vergleichswerte, getrennt
 * vom org-spezifischen (Stunden-)Mindestlohn in `minimum_wages`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('minimum_wage_references', function (Blueprint $table): void {
            $table->id();
            $table->string('country', 2);                 // ISO-3166 Alpha-2 (Eurostat-geo)
            $table->date('valid_from');                   // Halbjahres-Stichtag (S1→01-01, S2→07-01)
            $table->decimal('monthly_amount', 10, 2);     // Monatlicher Mindestlohn
            $table->string('currency', 3)->default('EUR');
            $table->string('source', 32)->default('eurostat');
            $table->timestamps();

            $table->unique(['country', 'valid_from', 'currency']);
            $table->index('country');
        });
    }

    public function down(): void {
        Schema::dropIfExists('minimum_wage_references');
    }
};
