<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_220000_msgraph_oof_setting_to_plugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Platform\PluginSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1042: Der Schalter „Outlook-Abwesenheitsnotiz“ zieht aus den
 * Organisationseinstellungen (`settings.msgraph.oof_enabled`) in die
 * Einstellungen des Msgraph-Plugins (`oof_enabled`).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('plugin_settings')) {
            return;
        }

        foreach (DB::table('organizations')->whereNotNull('settings')->get(['id', 'settings']) as $organization) {
            $settings = json_decode((string) $organization->settings, true);
            if (! is_array($settings) || ! array_key_exists('msgraph', $settings)) {
                continue;
            }
            $enabled = filter_var(data_get($settings, 'msgraph.oof_enabled', false), FILTER_VALIDATE_BOOLEAN);
            if ($enabled) {
                $row = PluginSetting::query()->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('plugin_id', 'msgraph')
                    ->first() ?? new PluginSetting(['organization_id' => $organization->id, 'plugin_id' => 'msgraph', 'enabled' => false]);
                // Verschlüsselte Einstellungen nur über das Modell; ohne Beobachter, fachlich keine neue Änderung.
                $row->forceFill(['settings' => ['oof_enabled' => true] + (array) ($row->settings ?? [])])->saveQuietly();
            }

            unset($settings['msgraph']);
            DB::table('organizations')->where('id', $organization->id)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('plugin_settings')) {
            return;
        }

        foreach (PluginSetting::query()->withoutGlobalScopes()->where('plugin_id', 'msgraph')->get() as $row) {
            if (! (bool) (($row->settings ?? [])['oof_enabled'] ?? false)) {
                continue;
            }
            $current = DB::table('organizations')->where('id', $row->organization_id)->value('settings');
            $settings = is_string($current) ? (array) json_decode($current, true) : [];
            $settings['msgraph'] = ['oof_enabled' => '1'];
            DB::table('organizations')->where('id', $row->organization_id)->update(['settings' => json_encode($settings)]);
        }
    }
};
