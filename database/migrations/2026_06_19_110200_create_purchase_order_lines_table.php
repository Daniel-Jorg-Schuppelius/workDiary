<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_110200_create_purchase_order_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestellzeilen (Feature 048, E4). `received_qty` wird vom Wareneingang gegen die
 * Bestellung fortgeschrieben; Teil- und Überlieferung sind zulässig.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->string('supplier_sku', 128)->nullable();
            $table->string('description');
            $table->decimal('ordered_qty', 18, 4);
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->string('unit', 20)->default('Stk');
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index(['article_id', 'article_variant_id'], 'po_lines_article_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_order_lines');
    }
};
