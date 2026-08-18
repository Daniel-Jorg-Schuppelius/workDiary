<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_160000_create_cost_estimates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenermittlung nach DIN 276 / HOAI (Feature 109, MVP-646).
 *
 * Die HOAI kennt vier Stufen — **Kostenschätzung, Kostenberechnung,
 * Kostenanschlag, Kostenfeststellung**. Sie lösen einander nicht ab, sondern
 * stehen nebeneinander: Der Vergleich zwischen ihnen *ist* die
 * Kostenkontrolle. Deshalb trägt jede Ermittlung ihre Stufe und ihren Stand,
 * und es wird nie eine bestehende überschrieben.
 *
 * **Die Ermittlung hängt am Projekt, nicht am Leistungsverzeichnis.** Ein
 * Bauvorhaben wird als Ganzes ermittelt; ein LV ist ein Gewerk davon. Die
 * Kostengruppen-Auswertung eines LV zieht das Budget deshalb über das Projekt
 * heran — hängt das LV an keinem, bleibt die Budgetspalte leer statt falsch.
 *
 * Woher die Zahlen stammen, bleibt am Datensatz (`source`): `x51_import` für
 * eine fremde Ermittlung, `derived` für eine aus dem eigenen Bestand
 * erzeugte, `manual` für eine von Hand erfasste.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cost_estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            // Eine aus dem LV-Stand erzeugte Ermittlung weiß, woraus sie kam.
            $table->foreignId('bill_of_quantity_id')->nullable()
                ->constrained('bill_of_quantities', indexName: 'costest_boq_fk')->nullOnDelete();

            $table->string('name', 200);
            $table->string('stage', 30);
            $table->string('method', 40)->nullable();
            $table->date('determined_on');
            $table->string('currency', 3)->default('EUR');
            $table->string('source', 20)->default('manual');
            // Die Ausgabe, gegen die die Kostengruppen zu lesen sind — „310"
            // heißt 2008 etwas anderes als 2018.
            $table->foreignId('catalog_registry_id')->nullable()
                ->constrained('catalog_registries', indexName: 'costest_reg_fk')->nullOnDelete();
            $table->string('note', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'project_id', 'stage'], 'costest_org_prj_stage_idx');
        });

        Schema::create('cost_estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cost_estimate_id')->constrained()->cascadeOnDelete();

            // Die Nummer des Kostenelements bzw. der Kostengruppe.
            $table->string('code', 40)->nullable();
            $table->string('label', 300);
            $table->decimal('quantity', 15, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('parent_code', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['cost_estimate_id', 'code'], 'costitem_est_code_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cost_estimate_items');
        Schema::dropIfExists('cost_estimates');
    }
};
