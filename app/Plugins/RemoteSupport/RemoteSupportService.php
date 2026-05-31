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

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Asset, ExternalReference, Organization, RemotePendingSession, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, RemoteProvider, RemoteSession, TeamViewerClient};
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
     * @return array{created: int, skipped: int, unmatched: int}
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
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importSessions(Organization $organization, array $config, iterable $sessions): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;

        $userId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            // Ohne buchbaren Benutzer lässt sich keine Zeit erfassen.
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        foreach ($sessions as $session) {
            match ($this->bookSession($organization, $config, $session, $userId)) {
                'created' => $created++,
                'unmatched' => $unmatched++,
                default => $skipped++,
            };
        }

        return ['created' => $created, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    /**
     * Verarbeitet genau eine Sitzung (für den zeilenweisen Wizard-Import):
     * matcht das Asset über die Geräte-ID und legt einen TimeEntry an, schiebt
     * unbekannte IDs in die Pending-Inbox oder überspringt bereits importierte.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return 'created'|'skipped'|'unmatched'
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

        $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable']);

        return 'created';
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
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::EXT_TYPE_SESSION)
            ->where('external_id', $this->sessionKey($session))
            ->exists();
    }

    /**
     * Bestimmt den Benutzer, dem importierte Zeiten zugeordnet werden:
     * konfigurierte default_user_id (in der Org) → Org-Owner → erster Org-Benutzer.
     */
    public function resolveBookingUserId(Organization $organization, ?int $defaultUserId): ?int {
        if ($defaultUserId !== null) {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey($defaultUserId)
                ->first();
            if ($user !== null) {
                return (int) $user->id;
            }
        }

        if ($organization->owner_id !== null) {
            return (int) $organization->owner_id;
        }

        $first = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        return $first !== null ? (int) $first->id : null;
    }

    private function createTimeEntry(Organization $organization, Asset $asset, RemoteSession $session, int $userId, bool $billable): ?TimeEntry {
        $customer = $asset->customer;
        $project = $customer?->defaultProjectOrCreate();
        if ($project === null) {
            // Ohne Kunde/Projekt kann keine projektbezogene Zeit gebucht werden.
            return null;
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

        // Idempotenz-Anker: verknüpft die anbieterseitige Session mit dem Eintrag.
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
            ],
            'synced_at' => now(),
        ]);

        return $entry;
    }

    /**
     * Legt eine unbekannte Verbindung als offene Pending-Session ab (Dedupe über
     * provider+session_id). Bereits zugewiesene/verworfene Einträge bleiben unberührt.
     */
    private function recordPending(Organization $organization, RemoteSession $session): void {
        $existing = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', $session->provider)
            ->where('session_id', $session->sessionId)
            ->first();

        if ($existing !== null) {
            return;
        }

        RemotePendingSession::query()->create([
            'organization_id' => $organization->id,
            'provider' => $session->provider,
            'remote_id' => $session->remoteId,
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
     * @return \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: ?string}>
     */
    public function openPendingGroups(Organization $organization): \Illuminate\Support\Collection {
        $groups = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
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
                    'count' => (int) $group->count(),
                    'minutes' => (int) $group->sum(fn(RemotePendingSession $s): int => $s->minutes()),
                    'first_seen' => $group->min('started_at'),
                    'last_seen' => $group->max('ended_at'),
                    'note' => $first->note,
                ];
            })
            ->values();

        /** @var \Illuminate\Support\Collection<int, object{provider: string, remote_id: string, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon, note: string|null}> $groups */
        return $groups;
    }

    /**
     * Weist alle offenen Pending-Sessions einer (provider, remote_id)-Gruppe dem
     * Asset zu: hinterlegt die Geräte-ID und materialisiert die Sessions als
     * Zeiteinträge (idempotent). Markiert die Pending-Sessions als imported.
     *
     * @return array{created: int, skipped: int}
     */
    public function assignPending(Organization $organization, string $provider, string $remoteId, Asset $asset, ?int $userId = null): array {
        $this->setRemoteId($asset, $provider, $remoteId);

        $userId ??= $this->resolveBookingUserId($organization, RemoteSupportConfig::resolve($organization->id)['default_user_id'] ?? null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<int, RemotePendingSession> $pending */
        $pending = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RemotePendingSession::STATUS_OPEN)
            ->where('provider', $provider)
            ->where('remote_id', $remoteId)
            ->get();

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
            $entry = $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable']);
            $row->update([
                'status' => RemotePendingSession::STATUS_IMPORTED,
                'time_entry_id' => $entry?->id,
                'resolved_at' => now(),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
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

    private function sessionKey(RemoteSession $session): string {
        return $session->provider . ':' . $session->sessionId;
    }

    private static function deviceType(string $provider): string {
        return self::DEVICE_TYPES[$provider] ?? throw new \InvalidArgumentException("Unknown remote provider: {$provider}");
    }
}
