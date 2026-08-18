<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_200000_add_excluded_buyers_to_tender_filter_profiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgeschlossene Auftraggeber im Suchprofil (Feature 108, MVP-630-Rest).
 *
 * Bewusst **kein** weiteres Ausschlusswort: Ausschlusswörter werden gegen
 * Titel, Kurztext und Auftraggeber zugleich verglichen — „Stadt Bonn" als
 * Ausschlusswort verwürfe auch eine Bekanntmachung, die die Stadt nur im Text
 * erwähnt. Der Auftraggeber ist ein eigenes Feld und wird auch so verglichen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tender_filter_profiles', function (Blueprint $table): void {
            $table->json('excluded_buyers')->nullable()->after('excluded_keywords');
        });
    }

    public function down(): void {
        Schema::table('tender_filter_profiles', function (Blueprint $table): void {
            $table->dropColumn('excluded_buyers');
        });
    }
};
