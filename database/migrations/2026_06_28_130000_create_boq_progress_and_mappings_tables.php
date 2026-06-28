<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_28_130000_create_boq_progress_and_mappings_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAEB-LV Verknüpfung & Aufmaß (Feature 049, MVP-083): Mengenfortschritt je
 * LV-Position (Aufmaß/Protokoll/Material/manuell) und optionale Verknüpfung der
 * Position mit dem kanonischen Stamm (Artikel/Material/Leistung) — polymorph,
 * ohne einen parallelen Artikelstamm zu erzeugen. Kurze, explizite Index-/
 * FK-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('boq_item_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqp_org_fk')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boqp_item_fk')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('source', 16)->default('manual'); // BoqProgressSource
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries', indexName: 'boqp_de_fk')->nullOnDelete();
            $table->foreignId('material_usage_id')->nullable()->constrained('material_usages', indexName: 'boqp_mu_fk')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['boq_item_id', 'captured_at'], 'boqp_item_captured_idx');
        });

        Schema::create('boq_item_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqm_org_fk')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items', indexName: 'boqm_item_fk')->cascadeOnDelete();
            $table->morphs('mappable'); // mappable_type/mappable_id (Article/Material/...)
            $table->decimal('factor', 18, 4)->default(1); // Mengenfaktor LV-Einheit → Stamm-Einheit
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['boq_item_id', 'mappable_type', 'mappable_id'], 'boqm_item_target_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_item_mappings');
        Schema::dropIfExists('boq_item_progress');
    }
};
