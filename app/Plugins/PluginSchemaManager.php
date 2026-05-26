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
use Illuminate\Support\Facades\{Artisan, Log};
use Throwable;

/**
 * Verwaltet den Schema-Lifecycle eines Plugins (Install / Upgrade / Uninstall).
 *
 * Plugins liefern einen Pfad zu eigenen Migrations ({@see Plugin::migrationsPath()})
 * und eine Schema-Version ({@see Plugin::schemaVersion()}). Der Manager ruft
 * `artisan migrate --path=...` mit `--realpath` auf und persistiert den
 * Stand in der `plugin_states`-Tabelle.
 *
 * Idempotent: `install()` und `upgrade()` führen nur tatsächlich noch nicht
 * ausgeführte Migrations aus (Laravel führt Buch über die `migrations`-Tabelle).
 */
class PluginSchemaManager {
    public function needsUpgrade(Plugin $plugin): bool {
        if ($plugin->migrationsPath() === null) {
            return false;
        }
        $state = PluginState::findOrInit($plugin->id());

        return $state->installed_version !== $plugin->schemaVersion();
    }

    /** Synonym für {@see upgrade()} bei Erstinstallation. */
    public function install(Plugin $plugin): bool {
        return $this->upgrade($plugin);
    }

    /**
     * Führt offene Migrations des Plugins aus und aktualisiert den State.
     * Gibt true zurück, wenn etwas getan wurde (oder zumindest versucht wurde);
     * false, wenn das Plugin kein eigenes Schema liefert.
     */
    public function upgrade(Plugin $plugin): bool {
        $path = $plugin->migrationsPath();
        if ($path === null) {
            return false;
        }

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

        $state = PluginState::findOrInit($plugin->id());
        $state->plugin_id = $plugin->id();
        $state->installed_version = $plugin->schemaVersion();
        if ($state->installed_at === null) {
            $state->installed_at = now();
        }
        $state->save();

        return true;
    }

    /**
     * Rollt die Plugin-Migrations zurück und entfernt den installed_version-Marker.
     */
    public function uninstall(Plugin $plugin): bool {
        $path = $plugin->migrationsPath();
        if ($path === null) {
            return false;
        }

        Artisan::call('migrate:rollback', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);

        $state = PluginState::findOrInit($plugin->id());
        $state->installed_version = null;
        $state->save();

        return true;
    }
}
