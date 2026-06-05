<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_11_120000_widen_time_entry_descriptions_to_text.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschreibungen aus historischen Importen (z. B. Toggl-Meeting-Notizen) sprengen
 * die bisherige VARCHAR-Grenze und ließen den Import an einem einzelnen Eintrag
 * scheitern ("Data too long for column 'description'"). description wird daher auf
 * TEXT erweitert — Manuell erfasste Einträge bleiben über die Formular-Validierung
 * (max:500) weiterhin schlank, importierte Notizen werden aber vollständig erhalten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });

        Schema::table('toggl_pending_entries', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->change();
        });

        Schema::table('toggl_pending_entries', function (Blueprint $table): void {
            $table->string('description')->nullable()->change();
        });
    }
};
