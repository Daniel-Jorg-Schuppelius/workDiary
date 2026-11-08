<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSessionImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Services;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Asset\Asset;
use App\Models\Classification\Tag;
use App\Models\Integration\{ExternalReference, ExternalReferenceAlias};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\PluginErrorRecorder;
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteProviders, RemoteSession};
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin};
use App\Plugins\Support\PersistsTimeImportInbox;
use App\Services\Integration\ProjectKeywordMatcher;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Throwable;

/**
 * Import & Buchung des Fernwartungs-Plugins: ruft die Verbindungs-Reports der
 * Anbieter ab, ordnet jede Sitzung über die Geräte-ID dem Asset →
 * Kunden-Standardprojekt zu und legt je Session einen TimeEntry an
 * ({@see import()}). Idempotenz über eine session-ExternalReference; unbekannte
 * IDs und Mehrkundengeräte wandern in die Pending-Inbox
 * ({@see RemotePendingAssignmentService}).
 */
class RemoteSessionImporter {
    use PersistsTimeImportInbox {
        resolveBookingUserId as public;
    }

    public const string EXT_TYPE_SESSION = 'session';

    /**
     * Statt Provider-Präfix in der Beschreibung („Anydesk — …") tragen
     * importierte Einträge Tags: Anbieter-Tag (Bezeichnung aus dem Register)
     * + generisches Remote-Tag — damit bleibt die Beschreibung sauber und die
     * Filterung möglich.
     */
    public const string REMOTE_TAG = 'Remote';

    protected function pluginId(): string {
        return RemoteSupportPlugin::ID;
    }

    /** Idempotenz-Anker ist hier die anbieterseitige Session. */
    protected function entryExternalType(): string {
        return self::EXT_TYPE_SESSION;
    }

    /**
     * Baut die aktiven Provider-Clients aus der Config — je Anbieter des
     * Registers, dessen Schalter gesetzt ist.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return array<int, RemoteProvider>
     */
    public function providersFor(array $config, ?int $organizationId = null): array {
        $providers = [];
        foreach (RemoteProviders::all() as $id => $definition) {
            $providerConfig = $config[$id] ?? null;
            if (! is_array($providerConfig) || empty($providerConfig['enabled'])) {
                continue;
            }
            $provider = $definition->make($providerConfig, $organizationId);
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Ruft die Verbindungs-Reports aller aktiven Provider für die Organisation
     * im Fenster [$from, $to] ab und legt je neuer Session einen TimeEntry an.
     *
     * Ein ausgefallener Anbieter stoppt die anderen nicht: sein Fehler wird
     * gezählt (`errors`), als Text mitgegeben (`error`) und im
     * Plugin-Fehlerrekorder festgehalten; was er bis zum Abbruch geliefert
     * hat (Seitenlimit), wird gebucht.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see RemoteSupportConfig::resolve()}
     * @return array{created: int, linked: int, skipped: int, unmatched: int, pending: int, errors: int, error: string}
     */
    public function import(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $sessions = [];
        $errors = [];
        foreach ($this->providersFor($config, (int) $organization->id) as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                foreach ($provider->fetchSessions($from, $to) as $session) {
                    $sessions[] = $session;
                }
            } catch (RemoteProviderException $e) {
                foreach ($e->sessions() as $session) {
                    $sessions[] = $session;
                }
                $errors[] = $this->recordProviderError($organization, $provider, $e);
            } catch (Throwable $e) {
                $errors[] = $this->recordProviderError($organization, $provider, $e);
            }
        }

        $result = $this->importSessions($organization, $config, $sessions);
        $result['errors'] = count($errors);
        $result['error'] = implode(' · ', $errors);

        return $result;
    }

    private function recordProviderError(Organization $organization, RemoteProvider $provider, Throwable $e): string {
        app(PluginErrorRecorder::class)->record(RemoteSupportPlugin::ID, 'sync:' . $provider->id(), $e, [], (int) $organization->id);

        return sprintf('%s: %s', RemoteProviders::label($provider->id()), $e->getMessage());
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

        // Chronologisch buchen: Anbieter liefern die neueste Verbindung zuerst,
        // die Verkettung ({@see precedingRemoteEntry()}) braucht aber den
        // Vorgänger, bevor die Folgeverbindung kommt.
        $ordered = collect($sessions)
            ->sortBy(static fn (RemoteSession $s): string => $s->startedAt->utc()->format('Y-m-d H:i:s') . ' ' . $s->sessionId)
            ->values();

        foreach ($ordered as $session) {
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
            $this->recordPending($organization, $session, $attemptAsset?->id, RemotePendingSessionStatus::Attempt);

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

        [, $linked] = $this->createTimeEntry($organization, $asset, $session, $userId, (bool) $config['default_billable'], mergeGapMinutes: self::mergeGapMinutes($config));

        return $linked ? 'linked' : 'created';
    }

    /**
     * Toleranz der Verkettung: Org-Einstellung aus {@see RemoteSupportConfig::resolve()},
     * sonst die Plugin-Vorgabe; 0 schaltet die Verkettung ab.
     *
     * @param  array<string, mixed>  $config
     */
    public static function mergeGapMinutes(array $config): int {
        return max(0, (int) ($config['merge_gap_minutes'] ?? config('plugins.remote-support.merge_gap_minutes', 30)));
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
     * internes Wartungsprojekt der Organisation (lazy angelegt). Public, weil
     * die Pending-Zuordnung dasselbe Fallback-Ziel braucht.
     */
    public function internalMaintenanceProject(Organization $organization): Project {
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
                // Eigene Geräte ohne Kunden: nie abrechenbar — explizit, damit
                // ein späterer Projekt-Schalter das Wartungsprojekt nicht
                // versehentlich in die Buchhaltungs-Arbeitsliste hebt.
                'billable' => false,
            ],
        );
    }

    private function matchAsset(Organization $organization, string $provider, string $remoteId): ?Asset {
        $ref = ExternalReference::query()
            ->forPlugin($organization, RemoteSupportPlugin::ID, RemoteDeviceRegistry::deviceType($provider))
            ->forExternalId($remoteId)
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
            RemoteDeviceRegistry::deviceType($provider),
            $remoteId,
        );

        return $model instanceof Asset ? $model : null;
    }

    /** Public, weil die Pending-Zuordnung dieselbe Idempotenz-Prüfung braucht. */
    public function sessionAlreadyImported(Organization $organization, RemoteSession $session): bool {
        return $this->alreadyImported($organization, $this->sessionKey($session));
    }

    /**
     * Public, weil die Pending-Zuordnung Sitzungen mit identischer Semantik
     * materialisiert (Überlappungs-Verknüpfung, Lead-Fenster, Verkettung,
     * Zeit-Kürzel).
     *
     * @param  ?int  $mergeGapMinutes  Toleranz der Verkettung; null = Plugin-Vorgabe ({@see mergeGapMinutes()})
     * @return array{0: ?TimeEntry, 1: bool}  [Eintrag, true = nur an vorhandene Zeit verknüpft]
     */
    public function createTimeEntry(Organization $organization, Asset $asset, RemoteSession $session, int $userId, bool $billable, ?Project $project = null, ?int $mergeGapMinutes = null): array {
        // Bei Mehrkundengeräten wird das Zielprojekt explizit übergeben; sonst
        // entscheidet die Sitzungsnotiz (Schlüsselwort-Zuordnung, MVP-483) und
        // erst danach das Projekt des Fremdkunden (Endkunden), das
        // Standardprojekt des Asset-Kunden oder — bei eigenen Geräten ohne
        // Kunden — das interne Wartungsprojekt.
        $project ??= app(ProjectKeywordMatcher::class)
            ->match($organization, $asset->foreignCustomer ?? $asset->customer, (string) $session->note)
            ?->project;
        // Ein ausdrücklich gewähltes Projekt (Mehrkundengerät, Schlüsselwort)
        // bindet die Verkettung an denselben Eintrag; das Standardprojekt nicht.
        $chosenProjectId = $project?->id;
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

        // Rückwärts-Lead-Fenster (Gegenstück zum FritzBox-Plugin): endet ein
        // Telefonat-Eintrag desselben Kunden ≤ attempt_lead_minutes vor dem
        // Sitzungsbeginn (erst der Anruf, dann der Sitzungsaufbau), verschmilzt
        // die Sitzung mit ihm statt doppelt zu buchen.
        $covering ??= $project->customer_id !== null
            ? $this->precedingCallEntry($organization, $session, $userId, (int) $project->customer_id)
            : null;

        // Verkettung: Reconnects und Wartezeiten (Update läuft, Techniker
        // trennt und kommt wieder) sind ein Vorgang — eine Verbindung
        // desselben Geräts ≤ merge_gap_minutes nach dem Ende eines
        // Fernwartungs-Eintrags verlängert ihn statt neu zu buchen. Kommt die
        // frühere Verbindung später an (CSV-Export neueste zuerst), zieht sie
        // den Beginn des schon gebuchten Nachfolgers vor.
        $gapMinutes = $mergeGapMinutes ?? self::mergeGapMinutes([]);
        $covering ??= $this->adjacentRemoteEntry($organization, $asset, $session, $userId, $project, $chosenProjectId, $gapMinutes, following: false);
        $covering ??= $this->adjacentRemoteEntry($organization, $asset, $session, $userId, $project, $chosenProjectId, $gapMinutes, following: true);

        if ($covering !== null) {
            $this->absorbSession($organization, $covering, $session, $asset);

            return [$covering, true];
        }

        $description = trim(sprintf(
            '%s (%s)',
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
        $startedAt = $this->applyNoteTimeShorthand($startedAt, $session, Tz::ofOrganization($organization));

        $attributes = [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $startedAt->setTimezone(Tz::ofOrganization($organization))->toDateString(),
            // Eloquent speichert die Wanduhr des Offsets (TeamViewer liefert ISO mit Offset): UTC erzwingen.
            'started_at' => $startedAt->utc(),
            'ended_at' => self::minimumEnd($startedAt, $session->endedAt)->utc(),
            'kind' => TimeEntryKind::Work,
            'description' => $description,
        ];
        if (! $billable) {
            // default_billable aus → importierte Sitzungen nie abrechenbar.
            $attributes['billable'] = false;
        }
        // Sonst Attribut bewusst weglassen → TimeEntry-Boot erbt
        // effectiveBillable() des Projekts (Muster MatchingTimeImportService):
        // Sitzungen nicht abrechenbarer Kunden landen sonst als billable=true
        // in den Offenen Zeiten.

        $entry = TimeEntry::query()->create($attributes);

        foreach ($usedAttempts as $attemptRow) {
            $attemptRow->update([
                'status' => RemotePendingSessionStatus::Imported,
                'time_entry_id' => $entry->id,
                'resolved_at' => now(),
            ]);
        }

        $this->rememberSessionReference($organization, $session, $entry, $asset, linked: false);
        $this->applyRemoteTags($organization, $entry, $session->provider);

        return [$entry, false];
    }

    /**
     * Anbieter-Tag (AnyDesk/TeamViewer) + Remote-Tag setzen — additiv, damit
     * manuell vergebene Tags (z. B. am verschmolzenen Telefonat-Eintrag)
     * erhalten bleiben. Läuft auch im Scheduler, daher die org-explizite
     * Tag-Auflösung.
     */
    public function applyRemoteTags(Organization $organization, TimeEntry $entry, string $provider): void {
        $ids = [];
        foreach ([RemoteProviders::label($provider), self::REMOTE_TAG] as $name) {
            $ids[] = Tag::findOrCreateByNameForOrganization($name, (int) $organization->id)->id;
        }

        $entry->tags()->syncWithoutDetaching($ids);
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
                ->where('status', RemotePendingSessionStatus::Attempt)
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
    private function applyNoteTimeShorthand(CarbonImmutable $start, RemoteSession $session, string $timezone): CarbonImmutable {
        $note = trim((string) $session->note);
        if ($note === '') {
            return $start;
        }

        // Absolute Angabe: „seit 8h" — bezogen auf den Tag des Sitzungsbeginns.
        if (preg_match('/\bseit\s+(\d[\d:.]*\s*(?:h|uhr)?)/iu', $note, $match) === 1) {
            $clock = DateHelper::parseClockTimeShorthand(trim($match[1]));
            if ($clock !== null) {
                // Uhrzeit der Notiz ist Ortszeit.
                $candidate = $session->startedAt->setTimezone($timezone)->setTime($clock[0], $clock[1])->utc();
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

    /**
     * Telefonat-Eintrag (FritzBox-Import) desselben Benutzers UND Kunden, der
     * höchstens attempt_lead_minutes vor dem Sitzungsbeginn endete — der Anruf
     * war die Anbahnung der Fernwartung. Nur erweiterbare Einträge zählen
     * (nicht exportiert, Monat offen): sonst würde die Verknüpfung die
     * Sitzungszeit verschlucken → eigener Eintrag ist dann korrekt.
     */
    private function precedingCallEntry(Organization $organization, RemoteSession $session, int $userId, int $customerId): ?TimeEntry {
        $windowMinutes = max(0, (int) config('plugins.remote-support.attempt_lead_minutes', 15));
        if ($windowMinutes === 0) {
            return null;
        }

        $candidates = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('exported', false)
            ->where('ended_at', '>=', $session->startedAt->subMinutes($windowMinutes))
            ->where('ended_at', '<=', $session->startedAt)
            ->whereIn('project_id', Project::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $customerId)
                ->select('id'))
            ->orderByDesc('ended_at')
            ->get();

        foreach ($candidates as $entry) {
            if ($this->isCallEntry($organization, $entry) && ! $this->extensionLocked($organization, $entry, $session)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Fernwartungs-Eintrag desselben Benutzers, Kunden und Geräts, der
     * höchstens $gapMinutes vor dem Sitzungsbeginn endete (Vorgänger) bzw.
     * höchstens $gapMinutes nach dem Sitzungsende begann (Nachfolger, wenn die
     * frühere Verbindung später ankommt): die Verbindung ist ein Reconnect
     * oder die Rückkehr nach einer Wartezeit. Nur erweiterbare Einträge zählen
     * (nicht exportiert, Monat offen); ein ausdrücklich gewähltes Projekt der
     * Sitzung muss das des Nachbarn sein — eine Notiz mit Schlüsselwort auf
     * ein anderes Projekt ist ein eigener Vorgang.
     */
    private function adjacentRemoteEntry(Organization $organization, Asset $asset, RemoteSession $session, int $userId, Project $project, ?int $chosenProjectId, int $gapMinutes, bool $following): ?TimeEntry {
        if ($gapMinutes === 0) {
            return null;
        }

        $candidates = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('exported', false)
            ->when(
                $following,
                static fn ($q) => $q
                    ->where('started_at', '>=', $session->endedAt)
                    ->where('started_at', '<=', $session->endedAt->addMinutes($gapMinutes))
                    ->orderBy('started_at'),
                static fn ($q) => $q
                    ->where('ended_at', '>=', $session->startedAt->subMinutes($gapMinutes))
                    ->where('ended_at', '<=', $session->startedAt)
                    ->orderByDesc('ended_at'),
            )
            ->when(
                $chosenProjectId !== null,
                static fn ($q) => $q->where('project_id', $chosenProjectId),
                static fn ($q) => $q->whereIn('project_id', Project::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->when(
                        $project->customer_id !== null,
                        static fn ($p) => $p->where('customer_id', $project->customer_id),
                        static fn ($p) => $p->whereKey($project->id),
                    )
                    ->select('id')),
            )
            ->get();

        foreach ($candidates as $entry) {
            if ($this->isRemoteEntryOfAsset($organization, $entry, $asset) && ! $this->extensionLocked($organization, $entry, $session)) {
                return $entry;
            }
        }

        return null;
    }

    /** Trägt der Eintrag die Primärreferenz einer Sitzung dieses Geräts? */
    private function isRemoteEntryOfAsset(Organization $organization, TimeEntry $entry, Asset $asset): bool {
        $reference = ExternalReference::query()
            ->forPlugin($organization, RemoteSupportPlugin::ID, self::EXT_TYPE_SESSION)
            ->forReferenceable($entry)
            ->first();

        return $reference !== null && (int) ($reference->payload['asset_id'] ?? 0) === (int) $asset->id;
    }

    /**
     * Hängt eine Sitzung an einen vorhandenen Eintrag: Ende verlängern,
     * Notiz in die Beschreibung, Sitzung als Nachweis verknüpfen, Tags setzen.
     * Public, weil der Nachlauf `remote:merge-sessions` den Bestand mit
     * derselben Semantik verkettet.
     */
    public function absorbSession(Organization $organization, TimeEntry $entry, RemoteSession $session, Asset $asset): void {
        $this->extendEntryEnd($organization, $entry, $session, $asset);
        $this->rememberSessionReference($organization, $session, $entry, $asset, linked: true);
        $this->applyRemoteTags($organization, $entry, $session->provider);
    }

    /**
     * Zieht das Ende eines Eintrags auf das Sitzungsende (Anruf → Fernwartung
     * bzw. Reconnect = eine Abrechnung). Greift nur bei Telefonat-Einträgen
     * und Fernwartungs-Einträgen desselben Geräts; fremd erfasste Zeiten
     * bleiben autoritativ, exportierte Einträge und abgeschlossene Monate
     * werden nie modifiziert (dann bleibt es bei der Verknüpfung). Die Notiz
     * der Sitzung ergänzt die Beschreibung eines Fernwartungs-Eintrags.
     */
    private function extendEntryEnd(Organization $organization, TimeEntry $entry, RemoteSession $session, Asset $asset): void {
        if ((bool) $entry->exported || $this->extensionLocked($organization, $entry, $session)) {
            return;
        }

        $remote = $this->isRemoteEntryOfAsset($organization, $entry, $asset);
        if (! $remote && ! $this->isCallEntry($organization, $entry)) {
            return;
        }

        $dirty = false;
        if ($entry->ended_at !== null && $session->endedAt->getTimestamp() > $entry->ended_at->getTimestamp()) {
            $entry->ended_at = \Illuminate\Support\Carbon::instance($session->endedAt);
            $dirty = true;
        }

        // Nur Fernwartungs-Einträge wandern auch nach vorn: ein Telefonat hat
        // seinen Beginn vom Anruf.
        if ($remote && $entry->started_at !== null && $session->startedAt->getTimestamp() < $entry->started_at->getTimestamp()) {
            $entry->started_at = \Illuminate\Support\Carbon::instance($session->startedAt->utc());
            $entry->date = \Illuminate\Support\Carbon::instance($session->startedAt->setTimezone(Tz::ofOrganization($organization)));
            $dirty = true;
        }

        if ($remote) {
            $merged = self::mergeNoteIntoDescription((string) $entry->description, $session->note);
            if ($merged !== (string) $entry->description) {
                $entry->description = $merged;
                $dirty = true;
            }
        }

        if ($dirty) {
            $entry->save(); // minutes rechnet der saving-Hook neu
        }
    }

    /**
     * Notiz einer Folgeverbindung in die Beschreibung „Gerät (Notiz)“: der
     * Platzhalter ohne Notiz wird ersetzt, eine andere Notiz mit „ · “
     * angehängt, eine schon enthaltene nicht wiederholt.
     */
    public static function mergeNoteIntoDescription(string $description, ?string $note): string {
        $note = trim((string) $note);
        if ($note === '' || str_contains(mb_strtolower($description), mb_strtolower($note))) {
            return $description;
        }

        $placeholder = '(' . __('Fernwartungssitzung') . ')';
        if (str_ends_with($description, $placeholder)) {
            return substr($description, 0, -strlen($placeholder)) . '(' . $note . ')';
        }

        if (str_ends_with($description, ')')) {
            return substr($description, 0, -1) . ' · ' . $note . ')';
        }

        return $description . ' (' . $note . ')';
    }

    /**
     * Darf der Eintrag bis $until verlängert werden? Exportierte Einträge und
     * abgeschlossene Monate (Eintrags- und Zieltag) bleiben unverändert.
     */
    public function canExtend(Organization $organization, TimeEntry $entry, CarbonImmutable $until): bool {
        if ((bool) $entry->exported) {
            return false;
        }

        $probe = new RemoteSession('probe', 'probe', 'probe', $until, $until);

        return ! $this->extensionLocked($organization, $entry, $probe);
    }

    /**
     * Eine Verbindung unter einer Minute wird als eine Minute gebucht: der
     * Observer rundet Minuten ab, und ein Eintrag mit 0 Minuten ist in Liste
     * und Abrechnung wertlos. Die echte Dauer bleibt am Sitzungsnachweis.
     */
    public static function minimumEnd(CarbonImmutable $startedAt, CarbonImmutable $endedAt): CarbonImmutable {
        $floor = $startedAt->addMinute();

        return $endedAt < $floor ? $floor : $endedAt;
    }

    /** Trägt der Eintrag eine FritzBox-Anruf-Referenz (Primär oder Alias)? */
    private function isCallEntry(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->forPlugin($organization, \App\Plugins\Fritzbox\FritzboxPlugin::ID, 'call')
            ->forReferenceable($entry)
            ->exists()
            || ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', \App\Plugins\Fritzbox\FritzboxPlugin::ID)
                ->where('external_type', 'call')
                ->where('referenceable_type', $entry->getMorphClass())
                ->where('referenceable_id', $entry->getKey())
                ->exists();
    }

    /** Monatsabschluss-Guard für die Ende-Verlängerung (Eintrags- und Sitzungsende-Tag). */
    private function extensionLocked(Organization $organization, TimeEntry $entry, RemoteSession $session): bool {
        $user = \App\Models\Platform\User::query()->withoutGlobalScopes()->find($entry->user_id);
        if ($user === null) {
            return false;
        }

        $tz = Tz::ofOrganization($organization);
        $closure = app(\App\Services\TimeApproval\MonthClosureService::class);

        return ($entry->started_at !== null && $closure->isPeriodLockedForUser($user, CarbonImmutable::instance($entry->started_at)->setTimezone($tz)))
            || $closure->isPeriodLockedForUser($user, $session->startedAt->setTimezone($tz))
            || $closure->isPeriodLockedForUser($user, $session->endedAt->setTimezone($tz));
    }

    /** Idempotenz-Anker: verknüpft die anbieterseitige Session mit dem Eintrag. */
    private function rememberSessionReference(Organization $organization, RemoteSession $session, TimeEntry $entry, Asset $asset, bool $linked): void {
        // extref_unique erlaubt je Zeiteintrag nur EINE Session-Primärreferenz.
        // Weitere Sitzungen am selben Eintrag (mehrere Sessions innerhalb einer
        // erfassten Zeit) laufen als Alias; alreadyImported() kennt beide.
        $occupied = ExternalReference::query()
            ->forPlugin($organization, RemoteSupportPlugin::ID, self::EXT_TYPE_SESSION)
            ->forReferenceable($entry)
            ->exists();

        if ($occupied) {
            \App\Models\Integration\ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
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
    private function recordPending(Organization $organization, RemoteSession $session, ?int $assetId = null, RemotePendingSessionStatus $status = RemotePendingSessionStatus::Open): void {
        $existing = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', $session->provider)
            ->where('session_id', $session->sessionId)
            ->first();

        if ($existing !== null) {
            // Bestehende offene Sessions mit nachgelieferten Feldern (Alias, fehlender
            // Kommentar) anreichern; bereits zugewiesene/verworfene bleiben unberührt.
            if ($existing->status === RemotePendingSessionStatus::Open) {
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
            'started_at' => $session->startedAt->utc(),
            'ended_at' => $session->endedAt->utc(),
            'note' => $session->note,
            'status' => $status,
        ]);
    }

    private function sessionKey(RemoteSession $session): string {
        return $session->provider . ':' . $session->sessionId;
    }
}
