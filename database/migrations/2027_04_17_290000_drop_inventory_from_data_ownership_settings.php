<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_290000_drop_inventory_from_data_ownership_settings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bestände folgen der Bestandsführung (`inventory_mode`, `inventory_plugin_id`,
 * MVP-1592); der Matrix-Eintrag `data_ownership.inventory` war ein zweiter
 * Schalter ohne Wirkung und entfällt. Kein Rückweg: der Wert wirkte nie.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('organizations')
            ->whereNotNull('settings')
            ->whereLike('settings', '%data_ownership%')
            ->orderBy('id')
            ->select(['id', 'settings'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $settings = JsonHelper::decode((string) $row->settings, true);
                    if (! is_array($settings) || ! is_array($settings['data_ownership'] ?? null) || ! array_key_exists('inventory', $settings['data_ownership'])) {
                        continue;
                    }
                    unset($settings['data_ownership']['inventory']);
                    if ($settings['data_ownership'] === []) {
                        unset($settings['data_ownership']);
                    }
                    DB::table('organizations')->where('id', $row->id)->update(['settings' => JsonHelper::encode($settings)]);
                }
            });
    }

    public function down(): void {}
};
