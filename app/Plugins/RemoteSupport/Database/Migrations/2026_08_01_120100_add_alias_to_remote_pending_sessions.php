<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_01_120100_add_alias_to_remote_pending_sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AnyDesk liefert neben der numerischen Client-ID häufig einen Alias (Klartext-
 * Name), der oft auf den Rechnernamen schließen lässt. Dieser wird zusätzlich
 * gespeichert, um die Zuordnung in der Inbox zu erleichtern.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('remote_pending_sessions', function (Blueprint $table): void {
            $table->string('alias', 191)->nullable()->after('remote_id');
        });
    }

    public function down(): void {
        Schema::table('remote_pending_sessions', function (Blueprint $table): void {
            $table->dropColumn('alias');
        });
    }
};
