<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110900_add_workspace_lookup_to_plugin_settings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\PluginSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Indizierte Suchspalte für Zeiterfassungs-Webhooks (Sicherheitsscan
 * 2026-08-23, S-57).
 *
 * `TimeTrackingWebhookGate::organizationFor()` lud VOR der Signaturprüfung
 * **alle** aktiven Zeilen des Plugins und entschlüsselte deren Einstellungen
 * in PHP, nur um eine Workspace-ID zu vergleichen. Der Endpunkt ist
 * unauthentifiziert; der Aufwand wuchs damit linear mit der Zahl der
 * Mandanten, und zwar für jeden Aufruf, den irgendjemand auslöst.
 *
 * Die Spalte trägt ein HMAC über `plugin_id|workspace_id` mit dem APP_KEY —
 * kein Klartext, aber indizierbar. Gepflegt wird sie im Modell selbst
 * ({@see PluginSetting::booted()}), damit sie nicht an einer vergessenen
 * Schreibstelle veraltet.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('plugin_settings')) {
            return;
        }

        if (! Schema::hasColumn('plugin_settings', 'workspace_lookup')) {
            Schema::table('plugin_settings', function (Blueprint $table): void {
                $table->string('workspace_lookup', 64)->nullable()->after('enabled');
                $table->index('workspace_lookup', 'plugin_settings_workspace_lookup_idx');
            });
        }

        // Bestand nachziehen. Gelesen wird über das Modell (die Einstellungen
        // sind verschlüsselt), geschrieben aber direkt: `save()` würde die
        // Einstellungen neu verschlüsseln und Beobachter auslösen, obwohl sich
        // fachlich nichts ändert.
        PluginSetting::query()->withoutGlobalScopes()->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                try {
                    $settings = is_array($row->settings) ? $row->settings : [];
                } catch (\Throwable) {
                    // Zeile mit fremdem/altem APP_KEY: nicht lesbar, also auch
                    // nicht zuzuordnen. Die Suchspalte bleibt leer — der
                    // Webhook findet sie dann nicht, was richtig ist, und die
                    // Migration bricht deswegen nicht ab.
                    continue;
                }

                DB::table('plugin_settings')->where('id', $row->getKey())->update([
                    'workspace_lookup' => PluginSetting::workspaceLookup(
                        (string) $row->plugin_id,
                        (string) ($settings['workspace_id'] ?? ''),
                    ),
                ]);
            }
        });
    }

    public function down(): void {
        if (! Schema::hasTable('plugin_settings')) {
            return;
        }

        Schema::table('plugin_settings', function (Blueprint $table): void {
            $table->dropIndex('plugin_settings_workspace_lookup_idx');
            $table->dropColumn('workspace_lookup');
        });
    }
};
