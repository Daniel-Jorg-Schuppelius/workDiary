<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130000_create_articles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanonischer Artikelstamm (Feature 048, MVP-060): eine gemeinsame Grundlage
 * für Rohstoffe, Verbrauchsmaterial, Handelswaren, Halb-/Fertigerzeugnisse und
 * Leistungen — Quelle für Feature 047 (Fertigung) und das Lagerfeature. Der
 * Hauptartikel trägt vererbbare Standarddaten; bestands- und fertigungsführend
 * ist die konkrete Variante (article_variants). SKU-Hoheit über NumberAuthority.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('number', 64)->nullable(); // SKU des Hauptartikels
            $table->string('gtin', 14)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('consumable'); // ArticleType
            $table->string('base_unit', 20)->default('Stk');
            $table->string('tax_class', 40)->nullable();

            // Fachliche Flags (Standard aus type, aber explizit übersteuerbar).
            $table->boolean('stockable')->default(true);
            $table->boolean('purchasable')->default(true);
            $table->boolean('sellable')->default(true);
            $table->boolean('manufacturable')->default(false);
            $table->boolean('batch_required')->default(false);
            $table->boolean('serial_required')->default(false);
            $table->boolean('shelf_life_required')->default(false);

            $table->string('status', 12)->default('draft'); // ArticleStatus
            $table->foreignId('default_procedure_template_version_id')->nullable()
                ->constrained('procedure_template_versions')->nullOnDelete();

            $table->decimal('default_purchase_price', 13, 4)->nullable();
            $table->decimal('default_sale_price', 13, 4)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
            $table->index('type');
            $table->unique(['organization_id', 'number'], 'articles_org_number_unique');
            $table->unique(['organization_id', 'gtin'], 'articles_org_gtin_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('articles');
    }
};
