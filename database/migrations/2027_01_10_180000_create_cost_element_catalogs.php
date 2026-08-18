<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_180000_create_cost_element_catalogs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baukostenkatalog nach GAEB X50 (Feature 109, MVP-645).
 *
 * Ein Baukostenkatalog ist **kein Vorhaben, sondern ein Nachschlagewerk**:
 * „Außenwand, zweischalig, 36,5 cm — 320 €/m²". Er speist die frühen
 * HOAI-Stufen, für die WorkDiary sonst keine Zahlen hat, und liegt deshalb
 * neben den Kostenermittlungen, nicht in ihnen.
 *
 * **Der Kennwert ist eine Spanne, keine Zahl.** Ein Katalog nennt von, Mittel
 * und bis; nur den Mittelwert zu speichern verschwiege, wie sicher er ist.
 * Genau dafür hält das Format drei Felder bereit (`UPFrom`/`UPAvg`/`UPTo`).
 *
 * **Zwei Bauformen, dieselbe Struktur:** X50.1 nummeriert die Elemente in
 * Teilen (`ElePart`, aus der Hierarchie zusammengesetzt), X50.2 vollständig
 * (`EleNo`, „üblich bei Strukturen analog der DIN 276"). Welche vorlag, merkt
 * sich der Katalog — der Export muss dieselbe wählen, sonst liest die
 * Gegenseite andere Nummern.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cost_element_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name', 200);
            $table->string('edition', 40)->nullable();
            $table->date('valid_on')->nullable();
            $table->string('currency', 3)->default('EUR');
            // true = vollständige Nummern (X50.2 `EleNo`), false = Teilnummern
            // (X50.1 `ElePart`).
            $table->boolean('full_element_numbers')->default(true);
            $table->string('source', 20)->default('x50_import');
            $table->string('note', 500)->nullable();
            $table->boolean('active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'active'], 'costcat_org_active_idx');
        });

        Schema::create('cost_elements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cost_element_catalog_id')->constrained('cost_element_catalogs', indexName: 'costel_cat_fk')->cascadeOnDelete();

            $table->string('code', 40)->nullable();
            $table->string('label', 300);
            $table->string('unit', 20)->nullable();
            // Der Kennwert als Spanne — ein früher Wert ist eine Schätzung,
            // und die Ehrlichkeit darüber gehört mit gespeichert.
            $table->decimal('unit_price_from', 15, 4)->nullable();
            $table->decimal('unit_price_avg', 15, 4)->nullable();
            $table->decimal('unit_price_to', 15, 4)->nullable();
            $table->string('remark', 1000)->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('parent_code', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['cost_element_catalog_id', 'code'], 'costel_cat_code_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cost_elements');
        Schema::dropIfExists('cost_element_catalogs');
    }
};
