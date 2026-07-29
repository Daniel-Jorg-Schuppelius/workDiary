<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OpenProject\Services;

use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use App\Plugins\Support\{RemoteTimeWriter, TimeWritebackDispatcher};

/**
 * Rückrichtung nach OpenProject (PATCH/DELETE auf `/api/v3/time_entries/{id}`).
 *
 * Betrifft nur **importierte** Zeiten; die Rückbuchung neu erfasster Zeiten
 * bleibt der Export ({@see OpenProjectExportService}).
 */
class OpenProjectOutboxDispatcher extends TimeWritebackDispatcher {
    public function pluginId(): string {
        return OpenProjectPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = OpenProjectConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    protected function writer(int $organizationId): ?RemoteTimeWriter {
        $config = OpenProjectConfig::resolve($organizationId);
        if (! $config['enabled']) {
            return null;
        }

        $client = new OpenProjectApiClient($config['api_token'], $config['base_url']);

        return $client->isConfigured() ? $client : null;
    }
}
