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

use App\Models\IntegrationOutboxEntry;
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use App\Plugins\Support\{MirrorsCreatedEntries, RemoteTimeWriter, TimeWritebackDispatcher};

/**
 * Rückrichtung nach OpenProject (PATCH/DELETE auf `/api/v3/time_entries/{id}`).
 *
 * Betrifft nur **importierte** Zeiten; die Rückbuchung neu erfasster Zeiten
 * bleibt der Export ({@see OpenProjectExportService}).
 */
class OpenProjectOutboxDispatcher extends TimeWritebackDispatcher implements MirrorsCreatedEntries {
    public const OP_ENTRY_CREATE = 'openproject.entry.create';

    public function pluginId(): string {
        return OpenProjectPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = OpenProjectConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    /**
     * Sofort-Rückbuchung (Audit 2026-08, Welle 1.2) — eigenes Opt-in
     * `push_on_create`, weil die Rückbuchung den Eintrag unmittelbar als
     * exportiert markiert (kein Korrekturfenster, anders als der Toggl-Spiegel).
     */
    public function mirrorCreateEnabled(int $organizationId): bool {
        $config = OpenProjectConfig::resolve($organizationId);

        return $config['enabled'] && (bool) $config['push_on_create'];
    }

    public function createOperation(): string {
        return self::OP_ENTRY_CREATE;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        if ($entry->operation !== self::OP_ENTRY_CREATE) {
            return parent::dispatch($entry);
        }

        if (! $this->mirrorCreateEnabled($entry->organization_id)) {
            return true; // inzwischen deaktiviert → erledigt
        }

        // OpenProjectApiException läuft in den Outbox-Retry (Statusantwort = nicht angelegt).
        return $this->dispatchCreateVia($entry, app(OpenProjectExportService::class), OpenProjectConfig::resolve($entry->organization_id));
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
