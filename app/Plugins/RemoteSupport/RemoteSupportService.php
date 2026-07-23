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
use App\Models\{Asset, Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, Organization, Project, RemotePendingSession, TimeEntry};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, RemoteProvider, RemoteSession, TeamViewerClient};
use App\Plugins\Support\PersistsTimeImportInbox;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;

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
    /**
     * Hinterlegt eine Geräte-ID additiv: ein Gerät kann mehrere IDs je
     * Anbieter tragen (Neuinstallation → neue AnyDesk-ID, Zweitinstanz).
     * `extref_unique` erlaubt je Gerät nur EINE Primär-Referenz je Typ —
     * weitere IDs laufen als {@see ExternalReferenceAlias} (gleiches Muster
     * wie bei Session-Referenzen). Eine ID zeigt immer auf genau ein Gerät.
     */
    public function setRemoteId(Asset $asset, string $provider, string $remoteId): void {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            return;
        }

        $primary = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->first();

        if ($primary === null) {
            ExternalReference::query()->withoutGlobalScopes()->create([
                'organization_id' => $asset->organization_id,
                'plugin_id' => RemoteSupportPlugin::ID,
                'external_type' => self::deviceType($provider),
                'external_id' => $remoteId,
                'referenceable_type' => $asset->getMorphClass(),
                'referenceable_id' => $asset->getKey(),
                'synced_at' => now(),
            ]);

            // Die ID ist jetzt Primär-Referenz — ein etwaiger Alias derselben
            // ID (früheres Ziel) wäre widersprüchlich.
            $this->deviceAliasQuery($asset->organization_id, $provider)
                ->where('external_id', $remoteId)
                ->delete();

            return;
        }

        if ((string) $primary->external_id === $remoteId) {
            $primary->update(['synced_at' => now()]);

            return;
        }

        ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $asset->organization_id,
                'plugin_id' => RemoteSupportPlugin::ID,
                'external_type' => self::deviceType($provider),
                'external_id' => $remoteId,
            ],
            [
                'referenceable_type' => $asset->getMorphClass(),
                'referenceable_id' => $asset->getKey(),
            ],
        );
    }

    /**
     * Basis-Query für Geräte-ID-Aliasse eines Anbieters (mandanten-gefiltert).
     *
     * @return \Illuminate\Database\Eloquent\Builder<ExternalReferenceAlias>
     */
    private function deviceAliasQuery(?int $organizationId, string $provider): \Illuminate\Database\Eloquent\Builder {
        return ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider));
    }

    /** Entfernt eine bestimmte Geräte-ID — ohne $remoteId alle IDs des Anbieters. */
    public function forgetRemoteId(Asset $asset, string $provider, ?string $remoteId = null): void {
        ExternalReference::query()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->when($remoteId !== null, fn ($q) => $q->where('external_id', $remoteId))
            ->delete();

        $this->deviceAliasQuery($asset->organization_id, $provider)
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->when($remoteId !== null, fn ($q) => $q->where('external_id', $remoteId))
            ->delete();
    }

    /** Erste hinterlegte Geräte-ID des Anbieters (Kompatibilitäts-Helfer). */
    public function remoteId(Asset $asset, string $provider): ?string {
        return $this->remoteIds($asset, $provider)[0] ?? null;
    }

    /**
     * Alle Geräte-IDs des Anbieters für dieses Gerät: Primär-Referenz zuerst,
     * danach Alias-IDs (Zusatz-IDs aus Neuinstallation/Merge).
     *
     * @return array<int, string>
     */
    public function remoteIds(Asset $asset, string $provider): array {
        $primary = ExternalReference::query()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->orderBy('id')
            ->pluck('external_id');

        $aliases = $this->deviceAliasQuery($asset->organization_id, $provider)
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->orderBy('id')
            ->pluck('external_id');

        return $primary
            ->merge($aliases)
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Überführt die Fernwartungsdaten eines (doppelt angelegten) Geräts auf
     * das Zielgerät: alle Geräte-IDs (Primär-Referenzen wandern um; kollidiert
     * die Primär-Referenz mit einer vorhandenen des Ziels, wird die Quell-ID
     * zum Alias) sowie sämtliche Pending-Sitzungen. Gebuchte Zeiteinträge
     * bleiben unberührt; das Quellgerät wird NICHT gelöscht/archiviert.
     *
     * @return array{ids: int, sessions: int}
     */
    public function mergeRemoteDevice(Asset $source, Asset $target): array {
        if ($source->getKey() === $target->getKey() || (int) $source->organization_id !== (int) $target->organization_id) {
            return ['ids' => 0, 'sessions' => 0];
        }

        $ids = 0;

        $primaries = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(self::DEVICE_TYPES))
            ->where('referenceable_type', $source->getMorphClass())
            ->where('referenceable_id', $source->getKey())
            ->get();

        foreach ($primaries as $ref) {
            $targetHasPrimary = ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->where('external_type', $ref->external_type)
                ->where('referenceable_type', $target->getMorphClass())
                ->where('referenceable_id', $target->getKey())
                ->exists();

            if ($targetHasPrimary) {
                ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'organization_id' => $source->organization_id,
                        'plugin_id' => RemoteSupportPlugin::ID,
                        'external_type' => $ref->external_type,
                        'external_id' => $ref->external_id,
                    ],
                    [
                        'referenceable_type' => $target->getMorphClass(),
                        'referenceable_id' => $target->getKey(),
                    ],
                );
                $ref->delete();
            } else {
                $ref->update(['referenceable_id' => $target->getKey(), 'synced_at' => now()]);
            }
            $ids++;
        }

        $ids += ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(self::DEVICE_TYPES))
            ->where('referenceable_type', $source->getMorphClass())
            ->where('referenceable_id', $source->getKey())
            ->update(['referenceable_id' => $target->getKey()]);

        $sessions = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('asset_id', $source->getKey())
            ->update(['asset_id' => $target->getKey()]);

        return ['ids' => (int) $ids, 'sessions' => (int) $sessions];
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
        // AnyDesk erzeugt je Verbindungsversuch einen Datensatz — Reconnects/
        // abgebrochene Versuche kommen als 0-Sekunden-Sitzungen (start == end)
        // an. Klassifizieren statt buchen: als Verbindungsversuch dokumentieren.
        if ($session->endedAt <= $session->startedAt) {
            $attemptAsset = $this->matchAsset($organization, $session->provider, $session->remoteId);
            $this->recordPending($organization, $session, $attemptAsset?->id, RemotePendingSession::STATUS_ATTEMPT);

            return 'skipped';
        }

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

        if ($ref !== null) {
            $asset = $ref->referenceable;
            if ($asset instanceof Asset) {
                return $asset;
            }
        }

        // Zusatz-IDs (Neuinstallation, Merge) liegen als Alias.
        $model = ExternalReferenceAlias::resolveModel(
            (int) $organization->id,
            RemoteSupportPlugin::ID,
            self::deviceType($provider),
            $remoteId,
        );

        return $model instanceof Asset ? $model : null;
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

        // Verbindungsversuche unmittelbar vor der Sitzung belegen den
        // Tätigkeitsbeginn (Einwahl gehört zur Arbeit): Start vorziehen und
        // die Versuche dem Eintrag zuordnen.
        [$startedAt, $usedAttempts] = $this->extendStartByAttempts($organization, $session);

        // Zeit-Kürzel in der Sitzungsnotiz („+1h", „2h extra", „seit 8h")
        // ziehen den Beginn weiter vor — die Notiz bleibt als Beleg in der
        // Beschreibung stehen.
        $startedAt = $this->applyNoteTimeShorthand($startedAt, $session);

        $entry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $startedAt->toDateString(),
            'started_at' => $startedAt,
            'ended_at' => $session->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $billable,
        ]);

        foreach ($usedAttempts as $attemptRow) {
            $attemptRow->update([
                'status' => RemotePendingSession::STATUS_IMPORTED,
                'time_entry_id' => $entry->id,
                'resolved_at' => now(),
            ]);
        }

        $this->rememberSessionReference($organization, $session, $entry, $asset, linked: false);

        return [$entry, false];
    }

    /**
     * Zieht den Buchungsbeginn bis zum frühesten Verbindungsversuch vor, der
     * der Sitzung innerhalb des Toleranzfensters unmittelbar vorausgeht —
     * kettenfähig (Versuch vor Versuch), mit Iterationsdeckel.
     *
     * @return array{0: CarbonImmutable, 1: \Illuminate\Support\Collection<int, RemotePendingSession>}
     */
    private function extendStartByAttempts(Organization $organization, RemoteSession $session): array {
        $windowMinutes = max(0, (int) config('plugins.remote-support.attempt_lead_minutes', 15));
        $start = $session->startedAt;
        $used = collect();

        if ($windowMinutes === 0) {
            return [$start, $used];
        }

        for ($i = 0; $i < 10; $i++) {
            $batch = RemotePendingSession::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('status', RemotePendingSession::STATUS_ATTEMPT)
                ->where('provider', $session->provider)
                ->where('remote_id', $session->remoteId)
                ->where('started_at', '>=', $start->subMinutes($windowMinutes))
                ->where('started_at', '<', $start)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $used = $used->merge($batch);
            $earliest = CarbonImmutable::parse($batch->min('started_at'));
            if ($earliest >= $start) {
                break;
            }
            $start = $earliest;
        }

        return [$start, $used];
    }

    /**
     * Wendet Zeit-Kürzel aus der Sitzungsnotiz auf den Buchungsbeginn an:
     *
     *  - „seit 8h" / „seit 8:30": absolute Uhrzeit am Tag des Sitzungsbeginns
     *    (die Arbeit lief schon vor der Verbindung). Gewinnt gegen
     *    Dauer-Kürzel; ignoriert, wenn sie nicht VOR dem Beginn liegt.
     *  - „+1h" / „+30min" bzw. „2h extra" / „extra 2h": notierte Zusatzzeit,
     *    mehrere Kürzel summieren sich. Deckel: note_extra_max_minutes.
     */
    private function applyNoteTimeShorthand(CarbonImmutable $start, RemoteSession $session): CarbonImmutable {
        $note = trim((string) $session->note);
        if ($note === '') {
            return $start;
        }

        // Absolute Angabe: „seit 8h" — bezogen auf den Tag des Sitzungsbeginns.
        if (preg_match('/\bseit\s+(\d[\d:.]*\s*(?:h|uhr)?)/iu', $note, $match) === 1) {
            $clock = DateHelper::parseClockTimeShorthand(trim($match[1]));
            if ($clock !== null) {
                $candidate = $session->startedAt->setTime($clock[0], $clock[1]);
                if ($candidate < $start) {
                    return $candidate;
                }
            }
        }

        $duration = '(?:\d+(?:[.,]\d+)?\s*(?:std|min|m|h)(?:\d{1,2})?)';
        $extraMinutes = 0;

        // Plus-Form: „+1h", „+30min", „+1,5h".
        if (preg_match_all('/\+\s*(' . $duration . ')/iu', $note, $matches) > 0) {
            foreach ($matches[1] as $token) {
                $extraMinutes += (int) DateHelper::parseDurationToMinutes($token);
            }
        }

        // Wort-Form: „2h extra" bzw. „extra 2h" (Plus-Treffer nicht doppelt zählen).
        if (preg_match_all('/(?<![+\d,.])(' . $duration . ')\s+extra\b/iu', $note, $matches) > 0) {
            foreach ($matches[1] as $token) {
                $extraMinutes += (int) DateHelper::parseDurationToMinutes($token);
            }
        }
        if (preg_match_all('/\bextra\s+(' . $duration . ')/iu', $note, $matches) > 0) {
            foreach ($matches[1] as $token) {
                $extraMinutes += (int) DateHelper::parseDurationToMinutes($token);
            }
        }

        if ($extraMinutes <= 0) {
            return $start;
        }

        $cap = max(0, (int) config('plugins.remote-support.note_extra_max_minutes', 480));
        if ($cap > 0) {
            $extraMinutes = min($extraMinutes, $cap);
        }

        return $start->subMinutes($extraMinutes);
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
    private function recordPending(Organization $organization, RemoteSession $session, ?int $assetId = null, string $status = RemotePendingSession::STATUS_OPEN): void {
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
            'status' => $status,
        ]);
    }

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
