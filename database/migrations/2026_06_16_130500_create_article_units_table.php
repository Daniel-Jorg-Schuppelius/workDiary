<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130500_create_article_units_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikelbezogene Einheiten mit exaktem Faktor zur Basiseinheit (Feature 048,
 * MVP-060), z. B. 1 Rolle = 100 Meter. Bestände/Bewegungen werden intern in der
 * Basiseinheit geführt; eine Faktoränderung verändert keine historischen
 * Bewegungen (dort steht der Snapshot). Dimensionswechsel (Liter↔kg) nur mit
 * ausdrücklich gepflegtem Faktor. Mandantengrenze transitiv über den Artikel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label')->nullable();
            $table->string('kind', 12)->default('packaging'); // ArticleUnitKind
            $table->decimal('factor_to_base', 18, 8)->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['article_id', 'code'], 'article_units_code_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_units');
    }
};
