<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102700_add_category_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikel-Kategorie (Feature 107, W8 — Nutzer-Entscheidung 2026-08-16):
 * zweistufige Freitext-Kategorie am Artikelstamm. Primärer Treiber ist der
 * DATANORM-Export (Haupt-/Warengruppe im A-Satz plus WRG-Datei mit
 * Klartext-Labels); daneben nutzbar für Auswertungen und Margenregeln.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('category', 64)->nullable()->after('base_unit');
            $table->string('subcategory', 64)->nullable()->after('category');
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['category', 'subcategory']);
        });
    }
};
