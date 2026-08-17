<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103400_create_boq_catalog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalogzuordnungen und Teilmengen (Feature 109, MVP-586/588).
 *
 * GAEB transportiert Kostengruppe nach DIN 276, Leistungsbereich, Gebäude,
 * Kostenträger und die BIM-Kennung über **einen** Mechanismus: die
 * Katalogzuordnung. Deshalb eine Struktur statt einer Spalte je Katalogart —
 * eine `cost_group`-Spalte wäre Zufallsmodellierung und trüge weder die
 * Ausgabe der Norm noch die anteilige Zuordnung.
 *
 * Die Ausgabe steckt im Katalogtyp (`cost group DIN 276 2018-12` neben
 * `…-1 2008-12`): ein Schlüssel „310" ohne sie ist mehrdeutig, weil die Ausgabe
 * 2018 über 240 Kostengruppen geändert hat.
 *
 * Teilmengen sind kein Sonderfall: Die Fachdokumentation hängt die Zuordnung
 * ausdrücklich an den Mengensplit, weil eine Position zu mehreren Kostengruppen
 * oder Bauteilen gehören kann. Ohne sie wäre die Zuordnung bei geteilten
 * Positionen nicht unvollständig, sondern falsch.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('boq_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqc_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqc_boq_fk')->cascadeOnDelete();
            $table->string('catalog_key', 60);        // GAEB CtlgID
            $table->string('type', 60)->nullable();   // z. B. cost group DIN 276 2018-12
            $table->string('name', 120)->nullable();
            $table->string('assign_type', 20)->nullable(); // Pct | Abs | PctAbs
            $table->timestamps();

            $table->unique(['bill_of_quantity_id', 'catalog_key'], 'boqc_boq_key_uq');
        });

        Schema::create('boq_item_quantity_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqqs_org_fk')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boqqs_item_fk')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('percent', 9, 6)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['boq_item_id', 'position'], 'boqqs_item_pos_idx');
        });

        Schema::create('boq_catalog_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqca_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqca_boq_fk')->cascadeOnDelete();
            // Position, Abschnitt oder Teilmenge - derselbe Mechanismus überall.
            $table->morphs('assignable');
            $table->string('catalog_key', 60);
            $table->string('code', 60);
            $table->decimal('quantity', 18, 4)->nullable(); // Anteil, wenn der Katalog ihn zulässt
            $table->string('source', 16)->default('import'); // import | manual | rule
            $table->timestamps();

            // Trägt die Auswertung „Summe je Kostengruppe" ohne Umweg.
            $table->index(['bill_of_quantity_id', 'catalog_key', 'code'], 'boqca_boq_cat_code_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_catalog_assignments');
        Schema::dropIfExists('boq_item_quantity_splits');
        Schema::dropIfExists('boq_catalogs');
    }
};
