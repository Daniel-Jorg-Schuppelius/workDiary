<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_130200_create_article_option_values_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zulässige Optionswerte je Optionsdefinition (Feature 048, MVP-060), z. B.
 * „Rot"/„Blau" für „Farbe". Nicht mehr zulässige Werte werden deaktiviert,
 * nicht gelöscht. Mandantengrenze transitiv über den Artikel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('article_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_option_definition_id')->constrained('article_option_definitions')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['article_option_definition_id', 'code'], 'article_opt_val_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('article_option_values');
    }
};
