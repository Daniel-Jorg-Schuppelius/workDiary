<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_170000_create_boq_calculation_data.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalkulationsdaten nach GAEB X52 (Feature 109, MVP-647).
 *
 * X52 überträgt, **wie ein Einheitspreis zustande kam**: Die *Kostenarten*
 * stehen im Kopf des Leistungsverzeichnisses (Lohn, Material, Gerät, …), die
 * *Kostenansätze* an der Position. Beides gehört zusammen — ein Ansatz ohne
 * seine Kostenart ist eine Zahl ohne Bedeutung.
 *
 * **Die Kostenart trägt den Zuschlag, nicht der Ansatz.** Ein Betrieb schlägt
 * nach Art der Kosten zu (Lohn anders als Material), nicht je Position; das
 * Format bildet genau das ab.
 *
 * Der Schlüssel (`key`) ist der des kalkulierenden Systems — GAEB schreibt
 * hier keinen Katalog vor. Er wird deshalb übernommen, wie er kommt, und nie
 * übersetzt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('boq_cost_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqct_boq_fk')->cascadeOnDelete();

            $table->string('cost_key', 40);
            $table->string('description', 500)->nullable();
            $table->string('unit', 20)->nullable();
            // Zuschlag in Prozent; sechs Nachkommastellen, wie das Format sie
            // zulässt (tgDecimal_9_6).
            $table->decimal('markup_percent', 12, 6)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['bill_of_quantity_id', 'cost_key'], 'boqct_boq_key_uq');
        });

        Schema::create('boq_item_cost_approaches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boqca_item_fk')->cascadeOnDelete();

            // Verweis auf die Kostenart über deren Schlüssel — die Datei tut es
            // ebenso, und ein Fremdschlüssel überlebte den Reimport nicht.
            $table->string('cost_key', 40);
            $table->decimal('quantity', 15, 3)->nullable();
            // Ohne eigene Einheit gilt die der Kostenart (Schema-Anmerkung).
            $table->string('unit', 20)->nullable();
            $table->decimal('performance', 15, 3)->nullable();
            $table->decimal('value', 15, 3)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['boq_item_id', 'cost_key'], 'boqca_item_key_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_item_cost_approaches');
        Schema::dropIfExists('boq_cost_types');
    }
};
