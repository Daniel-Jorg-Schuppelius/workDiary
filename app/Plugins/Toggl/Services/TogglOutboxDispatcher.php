<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Services;

use App\Plugins\Support\{RemoteTimeWriter, TimeWritebackDispatcher};
use App\Plugins\Toggl\Sources\TogglApiClient;
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};

/**
 * Rückrichtung nach Toggl — Konflikterkennung, Outbox-Semantik und
 * Fingerabdruck-Pflege stecken in {@see TimeWritebackDispatcher}.
 */
class TogglOutboxDispatcher extends TimeWritebackDispatcher {
    public function pluginId(): string {
        return TogglPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = TogglConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    protected function writer(int $organizationId): ?RemoteTimeWriter {
        $config = TogglConfig::resolve($organizationId);
        if (! $config['enabled']) {
            return null;
        }

        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);

        return $client->isConfigured() ? $client : null;
    }
}
