<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemotePendingAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Models\{Asset, Customer, ForeignCustomer, Organization, Project, RemotePendingSession};
use App\Plugins\RemoteSupport\Providers\RemoteSession;
use App\Services\Integration\ProjectKeywordMatcher;
use Carbon\CarbonImmutable;

/**
 * Pending-/Shared-Zuordnung des Fernwartungs-Plugins: gruppiert offene
 * Sitzungen unbekannter Geräte-IDs ({@see openPendingGroups()}), bindet sie
 * beim Zuweisen an ein Asset und materialisiert sie als Zeiteinträge
 * ({@see assignPending()}); Sitzungen von Mehrkundengeräten werden je Sitzung
 * einem Kunden/Projekt zugeordnet ({@see assignSharedSessions()}). Buchung und
 * Idempotenz-Prüfung delegieren an den {@see RemoteSessionImporter}.
 */
class RemotePendingAssignmentService {
    public function __construct(
        private readonly RemoteDeviceRegistry $devices,
        private readonly RemoteSessionImporter $importer,
    ) {}

    /**
     * Offene Pending-Sessions der Organisation, gruppiert nach Provider + Geräte-ID.
     *
     * @return \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, alias: ?string, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: ?string, notes: array<int, string>}>
     */
    public function openPendingGroups(Organization $organization, ?string $search = null): \Illuminate\Support\Collection {
        $groups = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->whereNull('asset_id')
            ->orderByDesc('ended_at')
            ->get()
            ->groupBy(fn(RemotePendingSession $s): string => $s->provider . '|' . $s->remote_id)
            ->map(function ($group): object {
                /** @var \Illuminate\Support\Collection<int, RemotePendingSession> $group */
                $first = $group->first();
                assert($first instanceof RemotePendingSession);

                return (object) [
                    'provider' => (string) $first->provider,
                    'remote_id' => (string) $first->remote_id,
                    'alias' => $group->pluck('alias')->first(fn($a): bool => $a !== null && $a !== ''),
                    'count' => (int) $group->count(),
                    'minutes' => (int) $group->sum(fn(RemotePendingSession $s): int => $s->minutes()),
                    'first_seen' => $group->min('started_at'),
                    'last_seen' => $group->max('ended_at'),
                    'note' => $first->note,
                    'notes' => $group
                        ->pluck('note')
                        ->filter(fn($n): bool => $n !== null && trim((string) $n) !== '')
                        ->map(fn($n): string => trim((string) $n))
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        if ($search !== null && trim($search) !== '') {
            $needle = mb_strtolower(trim($search));
            $groups = $groups
                ->filter(fn (object $g): bool => self::matchesSearch($needle, [
                    (string) $g->remote_id,
                    (string) ($g->alias ?? ''),
                    ...$g->notes,
                ]))
                ->values();
        }

        /** @var \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, alias: string|null, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: string|null, notes: array<int, string>}> $groups */
        return $groups;
    }

    /**
     * Case-insensitive Teilstring-Suche über mehrere Felder (In-Memory — die
     * Inbox-Gruppen sind bereits aggregiert geladen).
     *
     * @param  array<int, string|null>  $haystack
     */
    private static function matchesSearch(string $needle, array $haystack): bool {
        foreach ($haystack as $value) {
            if ($value !== null && $value !== '' && str_contains(mb_strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Weist alle offenen Pending-Sessions einer (provider, remote_id)-Gruppe dem
     * Asset zu: hinterlegt die Geräte-ID und materialisiert die Sessions als
     * Zeiteinträge (idempotent). Markiert die Pending-Sessions als imported.
     *
     * @return array{created: int, linked: int, skipped: int}
     */
    public function assignPending(Organization $organization, string $provider, string $remoteId, Asset $asset, ?int $userId = null): array {
        $this->devices->setRemoteId($asset, $provider, $remoteId);

        /** @var \Illuminate\Database\Eloquent\Collection<int, RemotePendingSession> $pending */
        $pending = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->where('provider', $provider)
            ->where('remote_id', $remoteId)
            ->get();

        // Mehrkundengeräte: nichts automatisch buchen. Offene Sitzungen
        // werden ans Gerät gebunden und bleiben offen — sie erscheinen im
        // Einzelzuordnungs-Reiter der Inbox.
        if ($this->importer->requiresPerSessionAssignment($asset)) {
            $parked = 0;
            $skipped = 0;

            /** @var RemotePendingSession $row */
            foreach ($pending as $row) {
                $session = new RemoteSession(
                    provider: $row->provider,
                    sessionId: $row->session_id,
                    remoteId: $row->remote_id,
                    startedAt: CarbonImmutable::parse($row->started_at),
                    endedAt: CarbonImmutable::parse($row->ended_at),
                    note: $row->note,
                );

                if ($this->importer->sessionAlreadyImported($organization, $session)) {
                    $row->update([
                        'status' => RemotePendingSession::STATUS_IMPORTED,
                        'resolved_at' => now(),
                    ]);
                    $skipped++;

                    continue;
                }

                $row->update(['asset_id' => $asset->id]);
                $parked++;
            }

            return ['created' => 0, 'linked' => 0, 'skipped' => $skipped, 'pending' => $parked];
        }

        $userId ??= $this->importer->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
        if ($userId === null) {
            return ['created' => 0, 'linked' => 0, 'skipped' => 0];
        }

        $created = 0;
        $linked = 0;
        $skipped = 0;

        /** @var RemotePendingSession $row */
        foreach ($pending as $row) {
            $session = new RemoteSession(
                provider: $row->provider,
                sessionId: $row->session_id,
                remoteId: $row->remote_id,
                startedAt: CarbonImmutable::parse($row->started_at),
                endedAt: CarbonImmutable::parse($row->ended_at),
                note: $row->note,
            );

            if ($this->importer->sessionAlreadyImported($organization, $session)) {
                $row->update([
                    'status' => RemotePendingSession::STATUS_IMPORTED,
                    'resolved_at' => now(),
                ]);
                $skipped++;

                continue;
            }

            $config = RemoteSupportConfig::resolve($organization->id);
            [$entry, $wasLinked] = $this->importer->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable']);
            $row->update([
                'status' => RemotePendingSession::STATUS_IMPORTED,
                'time_entry_id' => $entry?->id,
                'resolved_at' => now(),
            ]);
            $wasLinked ? $linked++ : $created++;
        }

        return ['created' => $created, 'linked' => $linked, 'skipped' => $skipped];
    }

    /** Verwirft alle offenen Pending-Sessions einer (provider, remote_id)-Gruppe. */
    public function dismissPending(Organization $organization, string $provider, string $remoteId): int {
        return RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->where('provider', $provider)
            ->where('remote_id', $remoteId)
            ->update([
                'status' => RemotePendingSession::STATUS_DISMISSED,
                'resolved_at' => now(),
            ]);
    }

    /**
     * Offene Sitzungen von Mehrkundengeräten (asset_id gesetzt), gruppiert je
     * Gerät. Jede Sitzung wird einzeln zur Zuordnung an einen Kunden angeboten.
     *
     * @return \Illuminate\Support\Collection<int, object{asset: Asset, sessions: \Illuminate\Support\Collection<int, RemotePendingSession>}>
     */
    public function openSharedSessions(Organization $organization, ?string $search = null): \Illuminate\Support\Collection {
        /** @var \Illuminate\Database\Eloquent\Collection<int, RemotePendingSession> $rows */
        $rows = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->whereNotNull('asset_id')
            ->with('asset')
            ->orderByDesc('started_at')
            ->get();

        // Dokumentierte Verbindungsversuche (0-Sekunden-Datensätze) je Gerät —
        // nur als Zähler, sie sind nicht buchbar.
        $attemptCounts = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_ATTEMPT)
            ->whereNotNull('asset_id')
            ->selectRaw('asset_id, COUNT(*) AS attempt_count')
            ->groupBy('asset_id')
            ->pluck('attempt_count', 'asset_id');

        $groups = $rows
            ->filter(fn(RemotePendingSession $s): bool => $s->asset instanceof Asset)
            ->groupBy(fn(RemotePendingSession $s): int => (int) $s->asset_id)
            ->map(function ($sessions) use ($attemptCounts): object {
                /** @var \Illuminate\Support\Collection<int, RemotePendingSession> $sessions */
                $first = $sessions->first();
                assert($first instanceof RemotePendingSession);
                $asset = $first->asset;
                assert($asset instanceof Asset);

                return (object) [
                    'asset' => $asset,
                    'sessions' => $sessions->values(),
                    'attempts' => (int) ($attemptCounts[(int) $asset->id] ?? 0),
                ];
            })
            ->values();

        if ($search !== null && trim($search) !== '') {
            $needle = mb_strtolower(trim($search));
            $groups = $groups
                ->filter(function (object $device) use ($needle): bool {
                    $haystack = [(string) $device->asset->name, (string) $device->asset->asset_no];
                    foreach ($device->sessions as $session) {
                        $haystack[] = (string) $session->remote_id;
                        $haystack[] = (string) ($session->alias ?? '');
                        $haystack[] = (string) ($session->note ?? '');
                    }

                    return self::matchesSearch($needle, $haystack);
                })
                ->values();
        }

        /** @var \Illuminate\Support\Collection<int, object{asset: Asset, sessions: \Illuminate\Support\Collection<int, RemotePendingSession>}> $groups */
        return $groups;
    }

    /**
     * Bucht mehrere markierte Sitzungen eines Mehrkundengeräts gesammelt auf einen
     * Kunden (optional konkretes Projekt). Bereits importierte werden übersprungen.
     *
     * @param  iterable<RemotePendingSession>  $rows
     * @return array{created: int, skipped: int}
     */
    public function assignSharedSessions(Organization $organization, iterable $rows, ?Customer $customer = null, ?Project $project = null, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): array {
        $userId ??= $this->importer->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($this->assignSharedSession($organization, $row, $customer, $project, $userId, $foreignCustomer)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Bucht genau eine Sitzung eines Mehrkundengeräts. Liefert false, wenn die
     * Sitzung bereits importiert war (dann nur als imported markiert). Ohne
     * übergebenes Projekt greift das Projekt des Fremdkunden (Endkunden) bzw.
     * das Standardprojekt des Kunden — ganz ohne Kunde das interne
     * Wartungsprojekt (eigene Firma).
     */
    public function assignSharedSession(Organization $organization, RemotePendingSession $row, ?Customer $customer = null, ?Project $project = null, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): bool {
        $asset = $row->asset;
        if (! $asset instanceof Asset) {
            return false;
        }

        // Wie beim Import: die Auswahl aus der Inbox gewinnt, danach entscheidet
        // die Notiz (MVP-483), zuletzt das Standardprojekt.
        $project ??= app(ProjectKeywordMatcher::class)
            ->match($organization, $foreignCustomer ?? $customer, (string) $row->note)
            ?->project;
        $project ??= $foreignCustomer?->defaultProjectOrCreate()
            ?? $customer?->defaultProjectOrCreate()
            ?? $this->importer->internalMaintenanceProject($organization);
        $userId ??= $this->importer->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
        if ($userId === null) {
            return false;
        }

        $session = new RemoteSession(
            provider: $row->provider,
            sessionId: $row->session_id,
            remoteId: $row->remote_id,
            startedAt: CarbonImmutable::parse($row->started_at),
            endedAt: CarbonImmutable::parse($row->ended_at),
            note: $row->note,
        );

        if ($this->importer->sessionAlreadyImported($organization, $session)) {
            $row->update([
                'status' => RemotePendingSession::STATUS_IMPORTED,
                'resolved_at' => now(),
            ]);

            return false;
        }

        $config = RemoteSupportConfig::resolve($organization->id);
        [$entry] = $this->importer->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable'], $project);
        $row->update([
            'status' => RemotePendingSession::STATUS_IMPORTED,
            'time_entry_id' => $entry?->id,
            'resolved_at' => now(),
        ]);

        return true;
    }

    /**
     * Verwirft eine einzelne offene Pending-Sitzung (für Mehrkundengeräte).
     *
     * @param  iterable<RemotePendingSession>  $rows
     */
    public function dismissSessions(iterable $rows): int {
        $count = 0;
        foreach ($rows as $row) {
            if ($row->status !== RemotePendingSession::STATUS_OPEN) {
                continue;
            }
            $row->update([
                'status' => RemotePendingSession::STATUS_DISMISSED,
                'resolved_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }
}
