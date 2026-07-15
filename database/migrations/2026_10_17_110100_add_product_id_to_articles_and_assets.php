<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_17_110100_add_product_id_to_articles_and_assets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produktmodell (MVP-369): additive nullable Typ-Verknüpfung an Artikel und
 * Assets + Backfill — je Org werden distinct getrimmte, case-insensitiv
 * deduplizierte (manufacturer, model)-Paare aus `assets` als `products`
 * angelegt und die Assets typisiert ({@see Product::backfillFromAssets()};
 * Artikel werden NICHT automatisch zugeordnet, kein Herstellerfeld).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('gtin')
                ->constrained('products')
                ->nullOnDelete();
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('model')
                ->constrained('products')
                ->nullOnDelete();
        });

        Product::backfillFromAssets();
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
