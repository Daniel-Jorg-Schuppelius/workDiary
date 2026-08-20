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

use App\Models\IntegrationOutboxEntry;
use App\Plugins\Kimai\{KimaiConfig, KimaiExportService, KimaiPlugin};
use App\Plugins\Kimai\Sources\KimaiApiClient;
use App\Plugins\Support\{MirrorsCreatedEntries, RemoteTimeWriter, TimeWritebackDispatcher};

/**
 * Rückrichtung nach Kimai (PATCH/DELETE auf `/api/timesheets/{id}`).
 *
 * Betrifft nur **importierte** Zeiten; die Rückbuchung neu erfasster Zeiten
 * bleibt der Export ({@see \App\Plugins\Kimai\KimaiExportService}, Flag
 * `export_enabled`).
 */
class KimaiOutboxDispatcher extends TimeWritebackDispatcher implements MirrorsCreatedEntries {
    public const OP_ENTRY_CREATE = 'kimai.entry.create';

    public function pluginId(): string {
        return KimaiPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = KimaiConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    /**
     * Sofort-Rückbuchung (Audit 2026-08, Welle 1.2) — eigenes Opt-in
     * `push_on_create`, weil die Rückbuchung den Eintrag unmittelbar als
     * exportiert markiert (kein Korrekturfenster, anders als der Toggl-Spiegel).
     */
    public function mirrorCreateEnabled(int $organizationId): bool {
        $config = KimaiConfig::resolve($organizationId);

        return $config['enabled'] && (bool) $config['export_enabled'] && (bool) $config['push_on_create'];
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

        // KimaiApiException läuft in den Outbox-Retry (Statusantwort = nicht angelegt).
        return $this->dispatchCreateVia($entry, app(KimaiExportService::class), KimaiConfig::resolve($entry->organization_id));
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
