<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai\Services;

use App\Plugins\Kimai\{KimaiConfig, KimaiPlugin};
use App\Plugins\Kimai\Sources\KimaiApiClient;
use App\Plugins\Support\{RemoteTimeWriter, TimeWritebackDispatcher};

/**
 * Rückrichtung nach Kimai (PATCH/DELETE auf `/api/timesheets/{id}`).
 *
 * Betrifft nur **importierte** Zeiten; die Rückbuchung neu erfasster Zeiten
 * bleibt der Export ({@see \App\Plugins\Kimai\KimaiExportService}, Flag
 * `export_enabled`).
 */
class KimaiOutboxDispatcher extends TimeWritebackDispatcher {
    public function pluginId(): string {
        return KimaiPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = KimaiConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    protected function writer(int $organizationId): ?RemoteTimeWriter {
        $config = KimaiConfig::resolve($organizationId);
        if (! $config['enabled']) {
            return null;
        }

        $client = new KimaiApiClient($config['api_token'], $config['base_url']);

        return $client->isConfigured() ? $client : null;
    }
}
