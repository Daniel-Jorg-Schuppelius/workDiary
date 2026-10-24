<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Api;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Seafile\SeafileConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Seafile-Client: für den Dokumenteingang aus den Plugin-
 * Einstellungen der Organisation, für das Backupziel aus seiner Verbindung.
 * Die SSRF-Prüfung macht die {@see PluginHttpFactory}.
 */
class SeafileClientFactory {
    public function isConfigured(int $organizationId): bool {
        return SeafileConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): SeafileClient {
        $config = SeafileConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_token'] === null) {
            throw new RuntimeException('Seafile ist für diese Organisation nicht eingerichtet (Adresse oder Token fehlt).');
        }

        return new SeafileClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_token']);
    }

    /** Adresse der Organisation (Instanzidentität), ohne Token. */
    public function baseUrl(int $organizationId): ?string {
        return SeafileConfig::resolve($organizationId)['base_url'];
    }

    public function forBackup(BackupTargetConnection $connection): SeafileClient {
        $serverUrl = rtrim(trim((string) $connection->server_url), '/');
        $token = (string) $connection->access_token;
        if ($serverUrl === '' || $token === '') {
            throw new RuntimeException('Seafile-Backupziel ohne Adresse oder Token.');
        }

        return new SeafileClient(app(PluginHttpFactory::class), $serverUrl, $token, SeafileConfig::allowPrivateBackupTargets());
    }
}
