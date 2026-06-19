<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_150100_create_stock_level_settings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mindest- und Meldebestand je Variante und Lagerort (Feature 048, MVP-068).
 * Unterschreitet die verfügbare Menge den Meldebestand, entsteht
 * Beschaffungsbedarf (Fehlmaterialprozess/offener Punkt).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_level_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('article_variant_id')->constrained('article_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('min_stock', 18, 4)->default(0);
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->timestamps();

            $table->index('organization_id');
            $table->unique(['article_variant_id', 'warehouse_id'], 'stock_level_variant_wh_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_level_settings');
    }
};
