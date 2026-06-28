<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_28_120000_create_gaeb_boq_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAEB-Leistungsverzeichnisse (Feature 049, MVP-081/082): Importlauf-Protokoll,
 * LV-Kopf, Abschnittshierarchie (Ordnungszahl-Knoten), Positionen und
 * Preis-Snapshots. Positionen erzeugen keinen parallelen Artikelstamm — die
 * Verknüpfung zum kanonischen Stamm folgt in MVP-083. Kurze, explizite
 * Index-/FK-Namen wegen des MySQL-64-Zeichen-Limits (SQLite-Dev verdeckt es).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('bill_of_quantities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boq_org_fk')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'boq_prj_fk')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries', indexName: 'boq_de_fk')->nullOnDelete();
            $table->string('name', 255);
            $table->string('external_id', 191)->nullable();    // DBNr/Projekt-GUID aus der GAEB-Datei
            $table->string('gaeb_version', 16)->nullable();
            $table->string('phase', 8)->nullable();            // GaebPhase (Snapshot)
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 24)->default('imported'); // BoqItemStatus (Kopf-Status)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'project_id'], 'boq_org_prj_idx');
        });

        Schema::create('gaeb_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'gimp_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->nullable()->constrained('bill_of_quantities', indexName: 'gimp_boq_fk')->nullOnDelete();
            $table->string('filename', 255);
            $table->string('file_hash', 64);
            $table->string('gaeb_version', 16)->nullable();   // z. B. "3.3"
            $table->string('phase', 8)->nullable();           // GaebPhase (DA-Code)
            $table->string('status', 24)->default('pending'); // GaebImportStatus
            $table->unsignedInteger('section_count')->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->json('preflight')->nullable();            // Fehler-/Warnungsprotokoll
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'gimp_org_status_idx');
        });

        Schema::create('boq_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqs_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqs_boq_fk')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('boq_sections', indexName: 'boqs_parent_fk')->cascadeOnDelete();
            $table->string('reference_no', 60);   // Ordnungszahl-Knoten (z. B. "01.02")
            $table->string('label', 255)->nullable();
            $table->unsignedInteger('position')->default(0); // Sortierreihenfolge
            $table->timestamps();

            $table->index(['bill_of_quantity_id', 'parent_id'], 'boqs_boq_parent_idx');
        });

        Schema::create('boq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqi_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqi_boq_fk')->cascadeOnDelete();
            $table->foreignId('boq_section_id')->nullable()->constrained('boq_sections', indexName: 'boqi_sec_fk')->nullOnDelete();
            $table->string('reference_no', 60);           // Ordnungszahl der Position (eindeutig je LV)
            $table->string('item_no', 60)->nullable();    // Positionsnummer falls abweichend
            $table->string('type', 16)->default('standard'); // BoqItemType
            $table->string('status', 24)->default('imported'); // BoqItemStatus
            $table->string('short_text', 500)->nullable();
            $table->longText('long_text')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 16)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();   // Preis-Snapshot (phasenabhängig)
            $table->decimal('total_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_addendum')->default(false);     // Nachtragskennzeichen
            $table->string('external_id', 191)->nullable();     // GAEB-Item-ID (RNoPart-Quelle)
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['bill_of_quantity_id', 'reference_no'], 'boqi_boq_refno_unique');
            $table->index(['organization_id', 'status'], 'boqi_org_status_idx');
        });

        Schema::create('boq_item_price_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boqps_item_fk')->cascadeOnDelete();
            $table->foreignId('gaeb_import_id')->nullable()->constrained('gaeb_imports', indexName: 'boqps_imp_fk')->nullOnDelete();
            $table->string('phase', 8)->nullable();   // GaebPhase
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('total_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['boq_item_id', 'captured_at'], 'boqps_item_captured_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_item_price_snapshots');
        Schema::dropIfExists('boq_items');
        Schema::dropIfExists('boq_sections');
        Schema::dropIfExists('gaeb_imports');
        Schema::dropIfExists('bill_of_quantities');
    }
};
