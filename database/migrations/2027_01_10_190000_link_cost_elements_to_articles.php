<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_190000_link_cost_elements_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennwert trifft Artikelstamm (Feature 109, MVP-645).
 *
 * Ein Baukostenkatalog sagt, was ein Bauteil **üblicherweise** kostet; der
 * Artikelstamm sagt, was es **bei uns** kostet. Die Verknüpfung stellt beides
 * nebeneinander: Wer einen Preis pflegt, sieht, ob er im Rahmen liegt.
 *
 * **Die Verknüpfung ersetzt keinen Preis.** Der Kennwert bleibt ein
 * Anhaltspunkt aus fremder Quelle — ihn in den Artikel zu schreiben hieße,
 * eine Marktspanne als eigene Kalkulation auszugeben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cost_elements', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->after('unit')
                ->constrained('articles', indexName: 'costel_article_fk')->nullOnDelete();
            $table->index('article_id', 'costel_article_idx');
        });
    }

    public function down(): void {
        Schema::table('cost_elements', function (Blueprint $table): void {
            $table->dropIndex('costel_article_idx');
            $table->dropConstrainedForeignId('article_id');
        });
    }
};
