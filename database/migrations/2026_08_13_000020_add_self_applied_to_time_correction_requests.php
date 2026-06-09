<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000020_add_self_applied_to_time_correction_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Korrekturanträge, die per Selbstkorrektur-Modus (Firmen-Einstellung)
 * direkt vom Mitarbeiter angewendet wurden – „selbst nachgetragen". Unterscheidet
 * sie in Inbox/Reports von genehmigten Anträgen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_correction_requests', function (Blueprint $table): void {
            $table->boolean('self_applied')->default(false)->after('applied_at');
        });
    }

    public function down(): void {
        Schema::table('time_correction_requests', function (Blueprint $table): void {
            $table->dropColumn('self_applied');
        });
    }
};
