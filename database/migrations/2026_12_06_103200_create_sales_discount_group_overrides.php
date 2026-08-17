<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103200_create_sales_discount_group_overrides.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenindividuelle Konditionen je Verkaufs-Rabattgruppe (Feature 107,
 * MVP-567): ein Override ersetzt für genau einen Kunden den org-weiten
 * Standardsatz der Gruppe. Wirkt im kundenindividuellen B2B-DATPREIS
 * (Nettopreis-Berechnung); `custom_price` je Artikel bleibt stärker.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sales_discount_group_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_discount_group_id')->constrained('sales_discount_groups', indexName: 'sdgo_group_fk')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers', indexName: 'sdgo_customer_fk')->cascadeOnDelete();
            $table->string('kind', 16)->default('discount'); // discount | factor | surcharge
            $table->decimal('value', 10, 4);
            $table->timestamps();

            $table->unique(['sales_discount_group_id', 'customer_id'], 'sdgo_group_customer_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_discount_group_overrides');
    }
};
