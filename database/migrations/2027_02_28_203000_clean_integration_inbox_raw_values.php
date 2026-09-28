<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_203000_clean_integration_inbox_raw_values.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Altbestand der Zuordnungs-Inbox: JTL/Lexoffice schrieben `article_variant`
 * statt des Morph-Alias, Outbox-Fehlschläge nach Queue-Timeout den
 * englischen Laravel-Text mit Job-Klassenname als Untertitel. Idempotent.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('integration_inbox_items')
            ->where('target_type', 'article_variant')
            ->update(['target_type' => 'article_variants']);

        $reasons = [
            '% has timed out.' => 'Zeitüberschreitung bei der Zustellung',
            '% has been attempted too many times.' => 'Zustellung nach zu vielen Versuchen abgebrochen',
        ];
        foreach ($reasons as $pattern => $reason) {
            DB::table('integration_inbox_items')
                ->where('dedupe_key', 'like', 'outbox-failed:%')
                ->where('display_subtitle', 'like', $pattern)
                ->update(['display_subtitle' => $reason]);
        }
    }

    public function down(): void {
        // Datenbereinigung — kein Rückweg.
    }
};
