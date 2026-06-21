<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_180000_create_procedure_material_requirements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionierte Stückliste/Rezeptur je Arbeitsplan-Version (Feature 047,
 * MVP-061). Eine Position referenziert einen Artikel (optional eine konkrete
 * Variante) und eine Menge mit Art (fest/pro Stück/Anteil), Einheit, Rundung
 * und optionalem Verschnittzuschlag. `position_code` ist stabil (für spätere
 * Varianten-Overrides). `is_tool` trennt Werkzeuge vom Verbrauchsmaterial.
 * Mandantengrenze transitiv über die Arbeitsplan-Version.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_material_requirements', function (Blueprint $table): void {
            $table->id();
            // Expliziter kurzer FK-Name: der Auto-Name überschreitet sonst das
            // 64-Zeichen-Limit von MySQL (SQLite-Dev verdeckt das).
            $table->foreignId('procedure_template_version_id')
                ->constrained('procedure_template_versions', indexName: 'pmr_ptv_fk')
                ->cascadeOnDelete();
            $table->string('position_code', 40);
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->string('quantity_kind', 12)->default('per_unit'); // QuantityKind
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('ratio_part', 18, 4)->nullable();
            $table->string('unit', 20);
            $table->string('rounding', 12)->default('none'); // none/up/down
            $table->decimal('waste_surcharge', 6, 3)->nullable(); // Prozent
            $table->boolean('is_tool')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['procedure_template_version_id', 'position_code'], 'proc_mat_req_position_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_material_requirements');
    }
};
