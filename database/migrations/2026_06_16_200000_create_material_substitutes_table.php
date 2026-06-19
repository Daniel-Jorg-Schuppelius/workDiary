<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_200000_create_material_substitutes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ersatzmaterial-Abweichung (Feature 048, Fehlmaterialprozess): dokumentiert
 * Sollartikel, Ersatzartikel, Menge, Begründung und Genehmigung. Ersatzmaterial
 * verändert NICHT rückwirkend die Stückliste – es wird als strukturierte,
 * auditierbare Abweichung festgehalten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('material_substitutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->foreignId('manufacturing_order_material_id')->nullable()->constrained('manufacturing_order_materials')->nullOnDelete();

            $table->foreignId('planned_article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('planned_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->foreignId('substitute_article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('substitute_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();

            $table->decimal('quantity', 18, 4);
            $table->string('status', 12)->default('requested'); // SubstituteStatus
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('manufacturing_order_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('material_substitutes');
    }
};
