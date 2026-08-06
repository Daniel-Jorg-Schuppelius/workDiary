<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120000_create_material_cost_allocations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialkosten-Zuordnung: ordnet einem Kunden (optional Projekt) einen Kosten-
 * betrag zu — entweder anteilig aus einem Lexoffice-Einkaufsbeleg (morph, ein
 * Beleg auf mehrere Kunden aufteilbar) oder als freier manueller Betrag
 * (source_type/source_id NULL). Basis der Gewinndarstellung (Umsatz − Material).
 * SoftDelete macht die Zuordnung reversibel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('material_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            // Quelle (morph): Lexoffice-Einkaufsbeleg o. Ä.; NULL = freier Betrag.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('allocated_amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('allocated_on');                 // Kostendatum (Monatszuordnung im Chart)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'customer_id'], 'mat_alloc_org_customer_idx');
            $table->index(['source_type', 'source_id'], 'mat_alloc_source_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('material_cost_allocations');
    }
};
