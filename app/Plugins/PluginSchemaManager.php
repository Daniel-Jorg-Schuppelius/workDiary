<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSchemaManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\Plugin;
use Illuminate\Support\Facades\{Artisan, Cache, DB, Log};
use Throwable;

/**
 * Verwaltet den Schema-Lifecycle eines Plugins (Install / Upgrade / Uninstall).
 * Repariert gemäß Review 2026-08, W6 (Entscheidung E-7: Option B):
 *
 *  - `needsUpgrade()` vergleicht per version_compare (String-Ungleichheit
 *    erkannte ein Downgrade fälschlich als Upgrade); Downgrades werden
 *    verweigert, sofern nicht explizit erzwungen.
 *  - `upgrade()` läuft unter Cache-Lock (parallele Prozesse migrieren nicht
 *    doppelt); `onInstall()` läuft VOR dem State-Save in einer Transaktion —
 *    wirft der Hook, gilt das Plugin nicht als installiert.
 *  - `uninstall()` rollt über `migrate:reset --path` ALLE Plugin-Migrationen
 *    zurück (nicht nur den letzten Batch) und leert installed_version UND
 *    installed_at — eine Re-Installation ist wieder ein Fresh-Install.
 *
 * Idempotent: Laravel führt über die `migrations`-Tabelle Buch; bereits
 * gelaufene Migrationen werden nicht erneut ausgeführt.
 */
class PluginSchemaManager {
    private const LOCK_SECONDS = 300;

    public function needsUpgrade(Plugin $plugin): bool {
        if ($plugin->migrationsPath() === null) {
            return false;
        }
        $state = PluginState::findOrInit($plugin->id());
        $installed = $state->installed_version;
        if ($installed === null) {
            return true;
        }
        if (version_compare($installed, $plugin->schemaVersion(), '>')) {
            // Downgrade: Code älter als das installierte Schema — kein
            // implizites „Upgrade" (die alte !==-Prüfung lief hier los).
            Log::warning('Plugin schema is newer than plugin code (downgrade?)', [
                'plugin_id' => $plugin->id(),
                'installed' => $installed,
                'code' => $plugin->schemaVersion(),
            ]);

            return false;
        }

        return version_compare($installed, $plugin->schemaVersion(), '<');
    }

    /** Synonym für {@see upgrade()} bei Erstinstallation. */
    public function install(Plugin $plugin): bool {
        return $this->upgrade($plugin);
    }

    /**
     * Führt offene Migrations des Plugins aus und aktualisiert den State.
     * Gibt true zurück, wenn etwas getan wurde (oder zumindest versucht wurde);
     * false, wenn das Plugin kein eigenes Schema liefert.
     *
     * @param  bool  $force  Erlaubt das Herabsetzen von installed_version (Downgrade).
     */
    public function upgrade(Plugin $plugin, bool $force = false): bool {
        $path = $plugin->migrationsPath();
        if ($path === null) {
            return false;
        }

        $state = PluginState::findOrInit($plugin->id());
        if (! $force && $state->installed_version !== null
            && version_compare($state->installed_version, $plugin->schemaVersion(), '>')) {
            throw new \RuntimeException(sprintf(
                'Downgrade verweigert: installiert ist %s, der Code liefert %s (plugin:upgrade --force).',
                $state->installed_version,
                $plugin->schemaVersion(),
            ));
        }

        // Lock gegen parallele Läufe (W6): zwei Prozesse (Boot-Auto-Upgrade in
        // local, CLI, UI-Button) dürfen nicht gleichzeitig migrieren.
        return Cache::lock('plugin-schema:' . $plugin->id(), self::LOCK_SECONDS)->block(10, function () use ($plugin, $path): bool {
            try {
                Artisan::call('migrate', [
                    '--path' => $path,
                    '--realpath' => true,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                Log::error('Plugin schema upgrade failed', [
                    'plugin_id' => $plugin->id(),
                    'path' => $path,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }

            // onInstall() VOR dem State-Save, gemeinsam transaktional: wirft
            // der Hook, gilt das Plugin nicht als installiert (A14).
            DB::transaction(function () use ($plugin): void {
                $state = PluginState::findOrInit($plugin->id());
                $state->plugin_id = $plugin->id();
                $freshInstall = $state->installed_at === null;
                if ($freshInstall) {
                    $plugin->onInstall();
                    $state->installed_at = now();
                }
                $state->installed_version = $plugin->schemaVersion();
                $state->save();
            });

            return true;
        });
    }

    /**
     * Rollt ALLE Plugin-Migrations zurück (batch-unabhängig) und setzt den
     * Installationszustand vollständig zurück.
     */
    public function uninstall(Plugin $plugin): bool {
        $path = $plugin->migrationsPath();
        if ($path === null) {
            return false;
        }

        return Cache::lock('plugin-schema:' . $plugin->id(), self::LOCK_SECONDS)->block(10, function () use ($plugin, $path): bool {
            Artisan::call('migrate:reset', [
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ]);

            DB::transaction(function () use ($plugin): void {
                $state = PluginState::findOrInit($plugin->id());
                $state->installed_version = null;
                $state->installed_at = null;
                $state->save();

                $plugin->onUninstall();
            });

            return true;
        });
    }
}
