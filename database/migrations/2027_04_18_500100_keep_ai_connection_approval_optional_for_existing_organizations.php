<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_500100_keep_ai_connection_approval_optional_for_existing_organizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * „Nur freigegebene KI-Verbindungen“ (Feature 025, MVP-1672) gilt ab Werk
 * (`config('ai.privacy.require_approval')` = true) — für Organisationen, die
 * es beim Einspielen schon gibt, bliebe sonst jede KI-Fähigkeit stehen, bis
 * jemand die Verbindungen freigibt. Deshalb steht für den Bestand ausdrücklich
 * `settings.ai.privacy.require_approval = false`; ein Org-Admin schaltet es im
 * Dialog „Datenschutz“ der KI-Dienste ein, die offenen Freigaben stehen bis
 * dahin im Arbeitsvorrat. Eine schon gesetzte Einstellung bleibt unberührt.
 *
 * Kein Rückweg: Ohne den Wert griffe die Vorgabe „an“ und legte die KI des
 * Bestands still — der ausdrückliche Wert schadet auch ohne diese Version nicht.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('organizations')
            ->orderBy('id')
            ->select(['id', 'settings'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $settings = $row->settings === null || $row->settings === '' ? [] : JsonHelper::decode((string) $row->settings, true);
                    if (! is_array($settings)) {
                        continue;
                    }
                    if (is_array($settings['ai'] ?? null) && is_array($settings['ai']['privacy'] ?? null)
                        && array_key_exists('require_approval', $settings['ai']['privacy'])) {
                        continue;
                    }
                    data_set($settings, 'ai.privacy.require_approval', false);
                    DB::table('organizations')->where('id', $row->id)->update(['settings' => JsonHelper::encode($settings)]);
                }
            });
    }

    public function down(): void {}
};
