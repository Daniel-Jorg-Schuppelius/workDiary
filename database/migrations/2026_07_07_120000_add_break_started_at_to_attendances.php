<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_07_120000_add_break_started_at_to_attendances.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laufende Pause als Zeitstempel (Feature 061, MVP-130, Rang 13): erlaubt einen
 * echten Pausen-Toggle am Terminal (Pause-Scan startet, nächster Scan beendet
 * sie und verbucht die verstrichenen Minuten auf `break_minutes_manual`). Null =
 * gerade keine laufende Pause; beim Ausstempeln wird eine offene Pause beendet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->timestamp('break_started_at')->nullable()->after('break_minutes_manual');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn('break_started_at');
        });
    }
};
