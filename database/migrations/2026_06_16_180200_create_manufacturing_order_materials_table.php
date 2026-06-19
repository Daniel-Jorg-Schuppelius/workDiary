<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_180200_create_manufacturing_order_materials_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufgelöster Materialbedarf eines Fertigungsauftrags (Feature 047, MVP-062/065).
 * Hält Sollmenge (aus Stückliste × Sollmenge), reservierte und tatsächlich
 * verbrauchte Menge sowie Bezeichnungs-/Einheiten-/Kostensnapshot getrennt.
 * Mandantengrenze transitiv über den Auftrag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('manufacturing_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->string('name_snapshot');
            $table->decimal('target_qty', 18, 4);
            $table->string('unit_snapshot', 20);
            $table->decimal('reserved_qty', 18, 4)->default(0);
            $table->decimal('consumed_qty', 18, 4)->default(0);
            $table->foreignId('stock_reservation_id')->nullable()->constrained('stock_reservations')->nullOnDelete();
            $table->decimal('cost_snapshot', 18, 4)->nullable();
            $table->string('calc_reason', 40)->nullable();
            $table->string('rounding', 12)->default('none');
            $table->boolean('is_tool')->default(false);
            $table->timestamps();

            $table->index('manufacturing_order_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('manufacturing_order_materials');
    }
};
