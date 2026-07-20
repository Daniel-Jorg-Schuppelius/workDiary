<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesBackupTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Concerns;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\PluginManager;
use App\Services\Backup\Exceptions\BackupPreflightException;

/**
 * Löst den Backup-Adapter (Plugin) einer Ziel-Verbindung auf
 * (Vollaudit 2026-07, N34) — ersetzt drei wörtlich identische Kopien in
 * Run-/Verify-/RestoreTest-Service. Fehlersemantik unverändert:
 * BackupPreflightException mit gleicher Meldung.
 */
trait ResolvesBackupTarget {
    protected function adapter(BackupTargetConnection $connection): BackupTarget {
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());
        if (! $plugin instanceof BackupTarget) {
            throw new BackupPreflightException(
                "Kein Backup-Adapter für Provider '{$connection->provider->value}' verfügbar.",
            );
        }

        return $plugin;
    }
}
