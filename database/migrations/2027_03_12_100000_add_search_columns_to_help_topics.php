<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_12_100000_add_search_columns_to_help_topics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hilfesuche (MVP-1079): `keywords` = Suchbegriffe aus dem Front-Matter,
 * `search_text` = gefaltete Suchwörter aus Titel, Suchbegriffen,
 * Überschriften und Text. Beides schreibt der Reindex; `search_text` setzt
 * das Modell beim Speichern.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('help_topics', function (Blueprint $table): void {
            $table->json('keywords')->nullable()->after('title');
            $table->text('search_text')->nullable()->after('headings');
        });
    }

    public function down(): void {
        Schema::table('help_topics', function (Blueprint $table): void {
            $table->dropColumn(['keywords', 'search_text']);
        });
    }
};
