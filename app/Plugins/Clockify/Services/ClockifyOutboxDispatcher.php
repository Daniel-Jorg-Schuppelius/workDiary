<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Services;

use App\Plugins\Clockify\{ClockifyConfig, ClockifyPlugin};
use App\Plugins\Clockify\Sources\ClockifyApiClient;
use App\Plugins\Support\{RemoteTimeWriter, TimeWritebackDispatcher};

/**
 * Rückrichtung nach Clockify (PUT/DELETE auf `/v1/workspaces/{ws}/time-entries/{id}`).
 */
class ClockifyOutboxDispatcher extends TimeWritebackDispatcher {
    public function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = ClockifyConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    protected function writer(int $organizationId): ?RemoteTimeWriter {
        $config = ClockifyConfig::resolve($organizationId);
        if (! $config['enabled']) {
            return null;
        }

        $client = new ClockifyApiClient(
            $config['api_key'],
            $config['base_url'],
            $config['reports_base_url'],
            $config['workspace_id'],
        );

        return $client->isConfigured() ? $client : null;
    }
}
