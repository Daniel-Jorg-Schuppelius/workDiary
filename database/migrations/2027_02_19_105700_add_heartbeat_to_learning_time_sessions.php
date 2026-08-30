<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105700_add_heartbeat_to_learning_time_sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lebenszeichen und Freigabeweg der Lernzeit (Feature 149, MVP-749).
 *
 * **Warum das nötig ist:** Lernzeit außerhalb der Arbeitszeit wird als
 * Anwesenheit gebucht. Ohne Lebenszeichen zählt ein offener Tab, den
 * niemand benutzt, als gearbeitete Zeit — das wäre eine falsche Angabe in
 * den Zeitkonten, nicht bloß eine ungenaue Statistik.
 *
 * `last_heartbeat_at` ist deshalb die Obergrenze: eine Sitzung ohne
 * Lebenszeichen endet dort, nicht beim Schließen des Browsers.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_time_sessions', function (Blueprint $table): void {
            $table->timestamp('last_heartbeat_at')->nullable()->after('ended_at');
            // pending|approved|rejected — nur bei Zeitpolitik „Freigabe nötig".
            $table->string('approval_status', 10)->nullable()->after('classification');
            $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->string('approval_note', 255)->nullable()->after('approved_at');

            $table->index(['organization_id', 'approval_status'], 'lrn_time_approval_idx');
        });
    }

    public function down(): void {
        Schema::table('learning_time_sessions', function (Blueprint $table): void {
            $table->dropIndex('lrn_time_approval_idx');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['last_heartbeat_at', 'approval_status', 'approved_at', 'approval_note']);
        });
    }
};
