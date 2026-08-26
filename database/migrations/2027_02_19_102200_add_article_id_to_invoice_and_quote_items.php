<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102200_add_article_id_to_invoice_and_quote_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umsatz je Produkt (Feature 140, MVP-705; Vollscan 2026-08-23, G1):
 * optionaler Artikelbezug auf Rechnungs- und Angebotspositionen. Die
 * Artikel-Löschung kappt nur den Bezug (SET NULL) — Belege bleiben unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->after('tour_id')
                ->constrained('articles', indexName: 'invoice_items_article_fk')->nullOnDelete();
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->after('quote_id')
                ->constrained('articles', indexName: 'quote_items_article_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropForeign('invoice_items_article_fk');
            $table->dropColumn('article_id');
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropForeign('quote_items_article_fk');
            $table->dropColumn('article_id');
        });
    }
};
