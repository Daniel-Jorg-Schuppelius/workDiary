<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_29_100000_add_time_unit_to_zammad_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeiteinheiten-Konvention für die Zeit-Rückbuchung ins Zammad-Ticket
 * (Feature 060, MVP-129, Rang 23). NULL = Rückkanal aus (opt-in, wie
 * `resolved_state`); `minute` sendet die erfassten Minuten direkt, `hour`
 * rechnet sie in Stunden um — je nach Zammad-„Time Accounting Unit"-Einstellung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->string('time_unit', 16)->nullable()->after('resolved_state');
        });
    }

    public function down(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->dropColumn('time_unit');
        });
    }
};
