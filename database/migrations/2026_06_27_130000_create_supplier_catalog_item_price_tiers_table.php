<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_27_130000_create_supplier_catalog_item_price_tiers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengenabhängige Preisstaffeln eines Katalogartikels (Feature 050, „Später").
 * Werden v. a. aus BMEcat (mehrere ARTICLE_PRICE mit LOWER_BOUND) befüllt. Der
 * Basispreis bleibt am Artikel (purchase_price); Staffeln sind Zusatzmengen.
 * Mandantengrenze transitiv über den Katalogartikel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_catalog_item_price_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_catalog_item_id')->constrained('supplier_catalog_items', indexName: 'scipt_item_fk')->cascadeOnDelete();
            $table->decimal('min_qty', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->unique(['supplier_catalog_item_id', 'min_qty'], 'scipt_item_minqty_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_catalog_item_price_tiers');
    }
};
