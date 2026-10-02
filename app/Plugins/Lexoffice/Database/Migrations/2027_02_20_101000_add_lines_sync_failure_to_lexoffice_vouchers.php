<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101000_add_lines_sync_failure_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (Review 2026-09-10, C7): Fehlermarker des Positions-Syncs.
 * Ein dauerhaft fehlschlagender Beleg blockierte bisher den Nachlauf — er
 * stand jede Runde wieder unter den neuesten 100. Jetzt: Zeitpunkt des
 * letzten Fehlschlags + Versuchszähler, der Sync wartet 2^n Stunden
 * (höchstens 7 Tage), bevor er den Beleg erneut anfragt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->timestamp('lines_sync_failed_at')->nullable()->after('lines_synced_at');
            $t->unsignedSmallInteger('lines_sync_attempts')->default(0)->after('lines_sync_failed_at');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $t): void {
            $t->dropColumn(['lines_sync_failed_at', 'lines_sync_attempts']);
        });
    }
};
