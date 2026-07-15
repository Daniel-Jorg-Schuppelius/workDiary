<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_17_110000_create_products_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produktmodell (produktmodell-konzept.md, MVP-369): Typ-Ebene
 * Hersteller-Modell zwischen Artikel (Handel/Lager) und Asset (Instanz).
 * Org-gescopte Stammdaten; `product_group_classification_id` verankert die
 * bestehende Klassifikations-Dimension am Typ (ersetzt sie nicht).
 *
 * Additiv. Kurze, explizite Index-Namen (MySQL 64-Zeichen-Limit); Feldlängen
 * 190 halten den Unique unter dem 3072-Byte-Indexlimit (utf8mb4).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('manufacturer', 190);
            $table->string('model', 190);
            // Anzeigename; Default „{manufacturer} {model}" setzt das Model.
            $table->string('name', 190);

            $table->foreignId('product_group_classification_id')
                ->nullable()
                ->constrained('classifications')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            // aktiv | auslaufend | abgekündigt (ProductStatus-Enum).
            $table->string('status', 16)->default('active');

            $table->timestamps();

            $table->unique(['organization_id', 'manufacturer', 'model'], 'products_org_manuf_model_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
