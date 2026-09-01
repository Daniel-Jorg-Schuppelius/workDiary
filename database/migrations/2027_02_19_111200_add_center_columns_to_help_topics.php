<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111200_add_center_columns_to_help_topics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hilfecenter (Feature 039, MVP-752): `modules` = Modul-Codes aus dem
 * Front-Matter (Sichtbarkeitsfilter, ausgewertet ab MVP-753), `headings` =
 * beim Reindex extrahierte h2/h3-Anker für das Inhaltsverzeichnis der
 * Vollseite. Beide werden ausschließlich von `help:reindex` geschrieben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('help_topics', function (Blueprint $table): void {
            $table->json('modules')->nullable()->after('audience');
            $table->json('headings')->nullable()->after('related');
        });
    }

    public function down(): void {
        Schema::table('help_topics', function (Blueprint $table): void {
            $table->dropColumn(['modules', 'headings']);
        });
    }
};
