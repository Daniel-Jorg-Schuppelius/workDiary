<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103000_add_assembly_minutes_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Montagezeit am Artikel (Feature 107, MVP-565): kalkulatorische
 * Arbeitszeit je Einheit in Minuten — gespeist aus DATANORM-ARBA-Sätzen
 * bei der Katalog-Übernahme, im Artikel-Formular pflegbar.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->decimal('assembly_minutes', 8, 2)->nullable()->after('sales_discount_group_id');
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('assembly_minutes');
        });
    }
};
