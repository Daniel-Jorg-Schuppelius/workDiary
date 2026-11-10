<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_16_100000_add_calendar_source_to_ical_subscriptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1531: Ein Kalender-Abo liest statt einer iCal-Adresse auch eine
 * Kalenderverbindung (CalDAV, Google, Microsoft 365). Die Quelle steht als
 * `<quelle>:<verbindung>` wie beim Einmalimport; die Adresse entfällt dann.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('ical_subscriptions', function (Blueprint $table): void {
            $table->text('url')->nullable()->change();
            $table->string('calendar_source', 120)->nullable()->after('url');
        });
    }

    public function down(): void {
        Schema::table('ical_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('calendar_source');
        });
    }
};
