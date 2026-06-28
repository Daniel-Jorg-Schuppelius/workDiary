<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_28_140000_create_boq_exports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAEB-Export-Protokoll (Feature 049, MVP-085): jeder erzeugte GAEB-Stand wird
 * mit Phase, Inhalts-Hash und Positionszahl auditierbar festgehalten. Gleicher
 * Inhalt → gleicher Hash (Idempotenz/Reproduzierbarkeit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('boq_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqe_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqe_boq_fk')->cascadeOnDelete();
            $table->string('phase', 8);          // GaebPhase (DA-Code)
            $table->string('gaeb_version', 16)->default('3.3');
            $table->string('file_hash', 64);     // sha256 des erzeugten XML
            $table->unsignedInteger('item_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bill_of_quantity_id', 'phase'], 'boqe_boq_phase_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_exports');
    }
};
