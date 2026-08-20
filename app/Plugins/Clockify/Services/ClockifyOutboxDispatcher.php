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

use App\Models\IntegrationOutboxEntry;
use App\Plugins\Clockify\{ClockifyConfig, ClockifyExportService, ClockifyPlugin};
use App\Plugins\Clockify\Exceptions\ClockifyApiException;
use App\Plugins\Clockify\Sources\ClockifyApiClient;
use App\Plugins\Support\{MirrorsCreatedEntries, RemoteTimeWriter, TimeWritebackDispatcher};
use Illuminate\Support\Facades\Log;

/**
 * Rückrichtung nach Clockify (PUT/DELETE auf `/v1/workspaces/{ws}/time-entries/{id}`).
 *
 * Zusätzlich (Audit 2026-08, Welle 1.2): der Create-Pfad des Spiegel-Exports
 * läuft über die Outbox ({@see MirrorsCreatedEntries}) statt nur über den
 * stündlichen `clockify:push` — der bleibt als Backfill bestehen.
 */
class ClockifyOutboxDispatcher extends TimeWritebackDispatcher implements MirrorsCreatedEntries {
    public const OP_ENTRY_CREATE = 'clockify.entry.create';

    public function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = ClockifyConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    public function mirrorCreateEnabled(int $organizationId): bool {
        $config = ClockifyConfig::resolve($organizationId);

        return $config['enabled'] && (bool) $config['export_enabled'];
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

        try {
            return $this->dispatchCreateVia($entry, app(ClockifyExportService::class), ClockifyConfig::resolve($entry->organization_id));
        } catch (ClockifyApiException $e) {
            if (! $e->isRateLimited()) {
                throw $e; // normale Outbox-Retry-Semantik
            }
            // Free-Plan (30 Requests/h): weitere Zustellversuche verbrennen nur
            // Quota für dieselbe Antwort — acknowledgen, clockify:push holt nach.
            Log::warning('Clockify-Create-Push: Rate-Limit, Backfill räumt auf.', [
                'organization_id' => $entry->organization_id,
                'outbox_id' => $entry->getKey(),
            ]);

            return true;
        }
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
