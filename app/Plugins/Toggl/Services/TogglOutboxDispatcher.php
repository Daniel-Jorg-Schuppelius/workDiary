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

use APIToolkit\Exceptions\{PaymentRequiredException, TooManyRequestsException};
use App\Models\{ExternalReference, IntegrationOutboxEntry, Organization, Project, TimeEntry};
use App\Plugins\Support\{MatchingTimeImportService, MirrorsCreatedEntries, RemoteTimeWriter, TimeWritebackDispatcher};
use App\Plugins\Toggl\Exceptions\TogglApiException;
use App\Plugins\Toggl\Sources\TogglApiClient;
use App\Plugins\Toggl\Support\TogglQuotaGuard;
use App\Plugins\Toggl\{TogglConfig, TogglExportService, TogglPlugin};
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Rückrichtung nach Toggl — Konflikterkennung, Outbox-Semantik und
 * Fingerabdruck-Pflege stecken in {@see TimeWritebackDispatcher}.
 *
 * Zusätzlich (MVP-463): der Create-Pfad des Spiegel-Exports läuft über die
 * Outbox ({@see MirrorsCreatedEntries}) statt nur über den stündlichen
 * `toggl:push` — der bleibt als Backfill bestehen; sein
 * whereNotExists-Guard verhindert Doppel-Pushes in beide Richtungen.
 */
class TogglOutboxDispatcher extends TimeWritebackDispatcher implements MirrorsCreatedEntries {
    public const OP_ENTRY_CREATE = 'toggl.entry.create';

    public function pluginId(): string {
        return TogglPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = TogglConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'];
    }

    public function mirrorCreateEnabled(int $organizationId): bool {
        $config = TogglConfig::resolve($organizationId);

        return $config['enabled'] && (bool) $config['export_enabled'];
    }

    public function createOperation(): string {
        return self::OP_ENTRY_CREATE;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        if ($entry->operation === self::OP_ENTRY_CREATE) {
            return $this->dispatchCreate($entry);
        }

        return parent::dispatch($entry);
    }

    /**
     * Update-Zustand um Tags und das gemappte Toggl-Projekt erweitern (G3) —
     * Projekt-Umzüge werden so mitgespiegelt; ohne Mapping bleibt project_id
     * weg (der Referenz-Payload-Fallback im Client greift weiter).
     *
     * @param  array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}  $state
     * @return array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool, tags?: list<string>, project_id?: int}
     */
    protected function updatePayload(TimeEntry $timeEntry, array $state): array {
        $state['tags'] = array_values($timeEntry->tags->pluck('name')->map(fn ($name): string => (string) $name)->all());

        if ($timeEntry->project_id !== null) {
            $togglProjectId = ExternalReference::query()
                ->forPlugin((int) $timeEntry->organization_id, TogglPlugin::ID, MatchingTimeImportService::EXT_TYPE_PROJECT_ID)
                ->where('referenceable_type', (new Project)->getMorphClass())
                ->where('referenceable_id', (int) $timeEntry->project_id)
                ->value('external_id');
            if (is_numeric($togglProjectId)) {
                $state['project_id'] = (int) $togglProjectId;
            }
        }

        return $state;
    }

    protected function writer(int $organizationId): ?RemoteTimeWriter {
        $config = TogglConfig::resolve($organizationId);
        if (! $config['enabled']) {
            return null;
        }

        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id'], $config['request_interval']);

        return $client->isConfigured() ? $client : null;
    }

    /**
     * Create-Pfad (G1/G2): Eignung prüft {@see TogglExportService::pushSingle()}
     * mit denselben Schutzlinien wie der Stunden-Batch (inkl. Referenz-Re-Check
     * gegen ein Rennen mit `toggl:push`). Unmapptes Projekt/fehlende Config ist
     * ein Drop, kein Fehler.
     */
    private function dispatchCreate(IntegrationOutboxEntry $outbox): bool {
        if (! $this->mirrorCreateEnabled($outbox->organization_id)) {
            return true; // inzwischen deaktiviert → erledigt
        }

        // Quota-Pause: kein API-Call — der Eintrag hat keine Toggl-Referenz,
        // der stündliche toggl:push holt ihn nach dem Reset nach.
        if (TogglQuotaGuard::isPaused($outbox->organization_id)) {
            return true;
        }

        $payload = $outbox->payload;
        $timeEntry = TimeEntry::query()
            ->withoutGlobalScopes()
            ->whereKey((int) ($payload['time_entry_id'] ?? 0))
            ->where('organization_id', $outbox->organization_id)
            ->first();
        $organization = Organization::query()->find($outbox->organization_id);
        if ($timeEntry === null || $organization === null) {
            return true; // inzwischen gelöscht — die Löschung braucht keinen Spiegel
        }

        try {
            app(TogglExportService::class)->pushSingle($organization, TogglConfig::resolve($organization->id), $timeEntry);
        } catch (ConnectException|ConnectionException $e) {
            // Der TogglApiClient läuft über das api-toolkit und wirft nach
            // ausgeschöpften Versuchen Guzzles ConnectException (Vollscan
            // 2026-08-23, B2: bisher wurde nur Laravels ConnectionException
            // gefangen — der Schutz griff nie).
            // Toggl v9 kennt keine Request-Id-Dedup: nach Transport-Timeout
            // NICHT blind wiederholen (Duplikat, falls der POST doch ankam).
            // Echte Fehlschläge holt der stündliche toggl:push nach; ein doch
            // angekommener Eintrag läuft als Unbekannter in die Import-Inbox.
            Log::warning('Toggl-Create-Push: Transportfehler, kein Retry (Backfill räumt auf).', [
                'organization_id' => $outbox->organization_id,
                'time_entry_id' => $timeEntry->getKey(),
                'error' => $e->getMessage(),
            ]);
        } catch (PaymentRequiredException|TooManyRequestsException $e) {
            // Stunden-Quota erschöpft: weitere Zustellversuche verbrennen nur
            // API-Calls für dieselbe Antwort → Zustellung pausieren; der
            // stündliche toggl:push holt die Einträge nach dem Reset nach.
            $this->pauseForQuota($outbox, $timeEntry, $e);
        } catch (TogglApiException $e) {
            // Der Toggl-Client wrappt POST-Fehler statusbasiert — Quota-Codes
            // pausieren wie oben, alles andere behält Retry/Kompensation.
            if ($e->status !== 402 && ! $e->isRateLimited()) {
                throw $e;
            }
            $this->pauseForQuota($outbox, $timeEntry, $e);
        }

        // pushSingle=false ist ein bewusster Drop (unmapptes Projekt o. ä.).
        return true;
    }

    /** Quota-Pause setzen und den ausgelassenen Push nachvollziehbar loggen. */
    private function pauseForQuota(IntegrationOutboxEntry $outbox, TimeEntry $timeEntry, \Throwable $e): void {
        $seconds = TogglQuotaGuard::pauseFromException($outbox->organization_id, $e);
        Log::warning('Toggl-Create-Push: Quota erschöpft, Zustellung pausiert.', [
            'organization_id' => $outbox->organization_id,
            'time_entry_id' => $timeEntry->getKey(),
            'pause_seconds' => $seconds,
        ]);
    }
}
