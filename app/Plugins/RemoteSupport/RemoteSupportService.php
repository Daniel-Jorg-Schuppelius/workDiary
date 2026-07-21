<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Asset, Customer, ExternalReference, ForeignCustomer, Organization, Project, RemotePendingSession, TimeEntry};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, RemoteProvider, RemoteSession, TeamViewerClient};
use App\Plugins\Support\PersistsTimeImportInbox;
use Carbon\CarbonImmutable;

/**
 * Kernlogik des Fernwartungs-Plugins:
 *  - Geräte-IDs (AnyDesk/TeamViewer) am Asset über die external_references-Tabelle
 *    pflegen ({@see setRemoteId()} / {@see forgetRemoteId()}).
 *  - Verbindungs-Reports der Anbieter abrufen, über die Geräte-ID dem Asset →
 *    Kunden-Standardprojekt zuordnen und je Session einen TimeEntry anlegen
 *    ({@see import()}). Idempotenz über eine session-ExternalReference.
 */
class RemoteSupportService {
    use PersistsTimeImportInbox {
        resolveBookingUserId as public;
    }

    /** Provider-Kennung → external_type des Geräte-Links. */
    public const DEVICE_TYPES = [
        AnyDeskClient::ID => 'anydesk_id',
        TeamViewerClient::ID => 'teamviewer_id',
    ];

    public const EXT_TYPE_SESSION = 'session';

    /**
     * Asset-Unterkategorien (category_code), die eine Fernwartungs-ID tragen
     * können. Nur für diese Geräte wird das Panel angeboten und nur ihnen lassen
     * sich offene Verbindungen zuweisen.
     *
     * @var list<string>
     */
    public const REMOTE_CATEGORY_CODES = ['workstation', 'server', 'notebook'];

    protected function pluginId(): string {
        return RemoteSupportPlugin::ID;
    }

    /** Idempotenz-Anker ist hier die anbieterseitige Session. */
    protected function entryExternalType(): string {
        return self::EXT_TYPE_SESSION;
    }

    /**
     * Hinterlegt die Geräte-ID eines Anbieters am Asset (Upsert).
     */
    public function setRemoteId(Asset $asset, string $provider, string $remoteId): void {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            $this->forgetRemoteId($asset, $provider);

            return;
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => RemoteSupportPlugin::ID,
                'external_type' => self::deviceType($provider),
                'referenceable_type' => $asset->getMorphClass(),
                'referenceable_id' => $asset->getKey(),
            ],
            [
                'organization_id' => $asset->organization_id,
                'external_id' => $remoteId,
                'synced_at' => now(),
            ],
        );
    }

    public function forgetRemoteId(Asset $asset, string $provider): void {
        ExternalReference::query()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->delete();
    }

    public function remoteId(Asset $asset, string $provider): ?string {
        $ref = ExternalReference::query()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->first();

        return $ref?->external_id;
    }

    /**
     * Baut die aktiven, konfigurierten Provider-Clients aus der Config.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return array<int, RemoteProvider>
     */
    public function providersFor(array $config): array {
        $providers = [];

        $ad = $config['anydesk'];
        if ($ad['enabled']) {
            $providers[] = new AnyDeskClient($ad['license_id'], $ad['api_key'], $ad['base_url']);
        }

        $tv = $config['teamviewer'];
        if ($tv['enabled']) {
            $providers[] = new TeamViewerClient($tv['api_key'], $tv['base_url']);
        }

        return $providers;
    }

    /**
     * Ruft die Verbindungs-Reports aller aktiven Provider für die Organisation
     * im Fenster [$from, $to] ab und legt je neuer Session einen TimeEntry an.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return array{created: int, linked: int, skipped: int, unmatched: int, pending: int}
     */
    public function import(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $sessions = [];
        foreach ($this->providersFor($config) as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            foreach ($provider->fetchSessions($from, $to) as $session) {
                $sessions[] = $session;
            }
        }

        return $this->importSessions($organization, $config, $sessions);
    }

    /**
     * Verarbeitet eine Menge bereits normalisierter Sitzungen (Provider-API oder
     * CSV-Import): ordnet jede über die Geräte-ID einem Asset zu und legt je neuer
     * Sitzung einen TimeEntry an; unbekannte IDs wandern in die Pending-Inbox.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @param  iterable<RemoteSession>  $sessions
     * @return array{created: int, linked: int, skipped: int, unmatched: int, pending: int}
     */
    public function importSessions(Organization $organization, array $config, iterable $sessions): array {
        $created = 0;
        $linked = 0;
        $skipped = 0;
        $unmatched = 0;
        $pending = 0;

        $userId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            // Ohne buchbaren Benutzer lässt sich keine Zeit erfassen.
            return ['created' => 0, 'linked' => 0, 'skipped' => 0, 'unmatched' => 0, 'pending' => 0];
        }

        foreach ($sessions as $session) {
            match ($this->bookSession($organization, $config, $session, $userId)) {
                'created' => $created++,
                'linked' => $linked++,
                'unmatched' => $unmatched++,
                'pending' => $pending++,
                default => $skipped++,
            };
        }

        return ['created' => $created, 'linked' => $linked, 'skipped' => $skipped, 'unmatched' => $unmatched, 'pending' => $pending];
    }

    /**
     * Verarbeitet genau eine Sitzung (für den zeilenweisen Wizard-Import):
     * matcht das Asset über die Geräte-ID und legt einen TimeEntry an, schiebt
     * unbekannte IDs in die Pending-Inbox oder überspringt bereits importierte.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return 'created'|'linked'|'skipped'|'unmatched'|'pending'
     */
    public function bookSession(Organization $organization, array $config, RemoteSession $session, int $userId): string {
        $asset = $this->matchAsset($organization, $session->provider, $session->remoteId);
        if ($asset === null) {
            $this->recordPending($organization, $session);

            return 'unmatched';
        }

        if ($this->sessionAlreadyImported($organization, $session)) {
            return 'skipped';
        }

        // Mehrkundengeräte werden nicht automatisch gebucht: Die Sitzung
        // wandert in die Inbox und wird dort je Sitzung zugeordnet.
        if ($this->requiresPerSessionAssignment($asset)) {
            $this->recordPending($organization, $session, $asset->id);

            return 'pending';
        }

        [, $linked] = $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable']);

        return $linked ? 'linked' : 'created';
    }

    /**
     * Nur explizit markierte Mehrkundengeräte (shared_remote) laufen über die
     * Einzelzuordnung — auch eigene Firmenrechner brauchen das Flag. Geräte
     * ohne Kunden UND ohne Flag sind interne Geräte: deren Sitzungen buchen
     * auf das interne Wartungsprojekt ({@see internalMaintenanceProject}).
     */
    public function requiresPerSessionAssignment(Asset $asset): bool {
        // Cast: frisch erzeugte Models ohne geladenes Attribut liefern null.
        return (bool) $asset->shared_remote;
    }

    /**
     * Buchungsziel für Sitzungen eigener Geräte ohne Kunden: kundenloses
     * internes Wartungsprojekt der Organisation (lazy angelegt).
     */
    private function internalMaintenanceProject(Organization $organization): Project {
        return Project::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'customer_id' => null,
                'name' => 'Interne Wartung',
            ],
            [
                'color' => (string) config('project.default_project.color', '#64748b'),
                'status' => ProjectStatus::Active->value,
                'is_default' => false,
            ],
        );
    }

    private function matchAsset(Organization $organization, string $provider, string $remoteId): ?Asset {
        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('external_id', $remoteId)
            ->first();

        if ($ref === null) {
            return null;
        }

        $asset = $ref->referenceable;

        return $asset instanceof Asset ? $asset : null;
    }

    private function sessionAlreadyImported(Organization $organization, RemoteSession $session): bool {
        return $this->alreadyImported($organization, $this->sessionKey($session));
    }

    /** @return array{0: ?TimeEntry, 1: bool}  [Eintrag, true = nur an vorhandene Zeit verknüpft] */
    private function createTimeEntry(Organization $organization, Asset $asset, RemoteSession $session, int $userId, bool $billable, ?Project $project = null): array {
        // Bei Mehrkundengeräten wird das Zielprojekt explizit übergeben; sonst
        // greift das Projekt des Fremdkunden (Endkunden), das Standardprojekt
        // des Asset-Kunden oder — bei eigenen Geräten ohne Kunden — das
        // interne Wartungsprojekt.
        $project ??= $asset->foreignCustomer?->defaultProjectOrCreate()
            ?? $asset->customer?->defaultProjectOrCreate()
            ?? $this->internalMaintenanceProject($organization);

        // Deckt eine bereits erfasste Zeit DESSELBEN Kunden die Sitzung ab
        // (z. B. Toggl-Import), wird die Sitzung nur als Nachweis verknüpft
        // statt doppelt gebucht. Kunden-Match ist Pflicht: parallele Sitzungen
        // bei anderen Kunden sind eigene, ungetrackte Arbeit.
        $covering = $project->customer_id !== null
            ? $this->findCoveringEntry($organization, $session, $userId, (int) $project->customer_id)
            : null;
        if ($covering !== null) {
            $this->rememberSessionReference($organization, $session, $covering, $asset, linked: true);

            return [$covering, true];
        }

        $description = trim(sprintf(
            '%s — %s (%s)',
            ucfirst($session->provider),
            $asset->name ?: $asset->asset_no,
            $session->note ?? __('Fernwartungssitzung'),
        ));

        $entry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $session->startedAt->toDateString(),
            'started_at' => $session->startedAt,
            'ended_at' => $session->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $billable,
        ]);

        $this->rememberSessionReference($organization, $session, $entry, $asset, linked: false);

        return [$entry, false];
    }

    /**
     * Vorhandener Zeiteintrag desselben Benutzers UND Kunden, der die Sitzung
     * zeitlich überlappt — bei mehreren gewinnt die größte Überlappung.
     */
    private function findCoveringEntry(Organization $organization, RemoteSession $session, int $userId, int $customerId): ?TimeEntry {
        $candidates = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('started_at', '<', $session->endedAt)
            ->where('ended_at', '>', $session->startedAt)
            ->whereIn('project_id', Project::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $customerId)
                ->select('id'))
            ->get();

        $best = null;
        $bestOverlap = 0;
        foreach ($candidates as $entry) {
            if ($entry->started_at === null || $entry->ended_at === null) {
                continue;
            }
            $overlap = min($entry->ended_at->getTimestamp(), $session->endedAt->getTimestamp())
                - max($entry->started_at->getTimestamp(), $session->startedAt->getTimestamp());
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $best = $entry;
            }
        }

        return $best;
    }

    /** Idempotenz-Anker: verknüpft die anbieterseitige Session mit dem Eintrag. */
    private function rememberSessionReference(Organization $organization, RemoteSession $session, TimeEntry $entry, Asset $asset, bool $linked): void {
        // extref_unique erlaubt je Zeiteintrag nur EINE Session-Primärreferenz.
        // Weitere Sitzungen am selben Eintrag (mehrere Sessions innerhalb einer
        // erfassten Zeit) laufen als Alias; alreadyImported() kennt beide.
        $occupied = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::EXT_TYPE_SESSION)
            ->where('referenceable_type', $entry->getMorphClass())
            ->where('referenceable_id', $entry->getKey())
            ->exists();

        if ($occupied) {
            \App\Models\ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'plugin_id' => RemoteSupportPlugin::ID,
                    'external_type' => self::EXT_TYPE_SESSION,
                    'external_id' => $this->sessionKey($session),
                ],
                [
                    'referenceable_type' => $entry->getMorphClass(),
                    'referenceable_id' => $entry->getKey(),
                ],
            );

            return;
        }

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => self::EXT_TYPE_SESSION,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => $this->sessionKey($session),
            'payload' => [
                'provider' => $session->provider,
                'remote_id' => $session->remoteId,
                'asset_id' => $asset->id,
                // true = Sitzung nur als Nachweis an vorhandene Zeit gekoppelt.
                'linked' => $linked,
            ],
            'synced_at' => now(),
        ]);
    }

    /**
     * Legt eine unbekannte Verbindung als offene Pending-Session ab (Dedupe über
     * provider+session_id). Bereits zugewiesene/verworfene Einträge bleiben unberührt.
     * Ist $assetId gesetzt, gehört die Sitzung zu einem bekannten Mehrkundengerät.
     */
    private function recordPending(Organization $organization, RemoteSession $session, ?int $assetId = null): void {
        $existing = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', $session->provider)
            ->where('session_id', $session->sessionId)
            ->first();

        if ($existing !== null) {
            // Bestehende offene Sessions mit nachgelieferten Feldern (Alias, fehlender
            // Kommentar) anreichern; bereits zugewiesene/verworfene bleiben unberührt.
            if ($existing->status === RemotePendingSession::STATUS_OPEN) {
                $dirty = false;

                if ($session->alias !== null && $session->alias !== '' && $existing->alias !== $session->alias) {
                    $existing->alias = $session->alias;
                    $dirty = true;
                }

                if (($existing->note === null || $existing->note === '') && $session->note !== null && $session->note !== '') {
                    $existing->note = $session->note;
                    $dirty = true;
                }

                if ($assetId !== null && $existing->asset_id !== $assetId) {
                    $existing->asset_id = $assetId;
                    $dirty = true;
                }

                if ($dirty) {
                    $existing->save();
                }
            }

            return;
        }

        RemotePendingSession::query()->create([
            'organization_id' => $organization->id,
            'asset_id' => $assetId,
            'provider' => $session->provider,
            'remote_id' => $session->remoteId,
            'alias' => $session->alias,
            'session_id' => $session->sessionId,
            'started_at' => $session->startedAt,
            'ended_at' => $session->endedAt,
            'note' => $session->note,
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    /**
     * Offene Pending-Sessions der Organisation, gruppiert nach Provider + Geräte-ID.
     *
     * @return \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, alias: ?string, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: ?string, notes: array<int, string>}>
     */
    public function openPendingGroups(Organization $organization): \Illuminate\Support\Collection {
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

        /** @var \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, alias: string|null, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: string|null, notes: array<int, string>}> $groups */
        return $groups;
    }

    /**
     * Weist alle offenen Pending-Sessions einer (provider, remote_id)-Gruppe dem
     * Asset zu: hinterlegt die Geräte-ID und materialisiert die Sessions als
     * Zeiteinträge (idempotent). Markiert die Pending-Sessions als imported.
     *
     * @return array{created: int, linked: int, skipped: int}
     */
    public function assignPending(Organization $organization, string $provider, string $remoteId, Asset $asset, ?int $userId = null): array {
        $this->setRemoteId($asset, $provider, $remoteId);

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
        if ($this->requiresPerSessionAssignment($asset)) {
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

                if ($this->sessionAlreadyImported($organization, $session)) {
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

        $userId ??= $this->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
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

            if ($this->sessionAlreadyImported($organization, $session)) {
                $row->update([
                    'status' => RemotePendingSession::STATUS_IMPORTED,
                    'resolved_at' => now(),
                ]);
                $skipped++;

                continue;
            }

            $config = RemoteSupportConfig::resolve($organization->id);
            [$entry, $wasLinked] = $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable']);
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
    public function openSharedSessions(Organization $organization): \Illuminate\Support\Collection {
        /** @var \Illuminate\Database\Eloquent\Collection<int, RemotePendingSession> $rows */
        $rows = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->whereNotNull('asset_id')
            ->with('asset')
            ->orderByDesc('started_at')
            ->get();

        $groups = $rows
            ->filter(fn(RemotePendingSession $s): bool => $s->asset instanceof Asset)
            ->groupBy(fn(RemotePendingSession $s): int => (int) $s->asset_id)
            ->map(function ($sessions): object {
                /** @var \Illuminate\Support\Collection<int, RemotePendingSession> $sessions */
                $first = $sessions->first();
                assert($first instanceof RemotePendingSession);
                $asset = $first->asset;
                assert($asset instanceof Asset);

                return (object) [
                    'asset' => $asset,
                    'sessions' => $sessions->values(),
                ];
            })
            ->values();

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
        $userId ??= $this->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
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

        $project ??= $foreignCustomer?->defaultProjectOrCreate()
            ?? $customer?->defaultProjectOrCreate()
            ?? $this->internalMaintenanceProject($organization);
        $userId ??= $this->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
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

        if ($this->sessionAlreadyImported($organization, $session)) {
            $row->update([
                'status' => RemotePendingSession::STATUS_IMPORTED,
                'resolved_at' => now(),
            ]);

            return false;
        }

        $config = RemoteSupportConfig::resolve($organization->id);
        [$entry] = $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable'], $project);
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

    private function sessionKey(RemoteSession $session): string {
        return $session->provider . ':' . $session->sessionId;
    }

    private static function deviceType(string $provider): string {
        return self::DEVICE_TYPES[$provider] ?? throw new \InvalidArgumentException("Unknown remote provider: {$provider}");
    }
}
