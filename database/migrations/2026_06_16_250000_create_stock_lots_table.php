<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_250000_create_stock_lots_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chargen-/Losregister (Feature 047/048, E2). Eine Charge je Variante ist über
 * `lot_no` eindeutig und trägt Herstell-/Mindesthaltbarkeitsdatum. Bewertungs-
 * und Bewegungsschichten referenzieren die Charge; die Entnahme nach
 * Verfallsdatum (FEFO) ordnet darüber.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->string('lot_no', 80);
            $table->date('mfg_date')->nullable();
            $table->date('best_before')->nullable();
            $table->string('supplier_ref', 128)->nullable();
            $table->string('status', 12)->default('active'); // active | blocked
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'article_variant_id', 'lot_no'], 'stock_lots_org_variant_no_uq');
            $table->index(['article_variant_id', 'best_before'], 'stock_lots_variant_bb_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_lots');
    }
};
