<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use App\Plugins\Fritzbox\Sources\{FritzboxCall, FritzboxCsvParser};
use App\Plugins\Support\PersistsTimeImportInbox;
use App\Services\TimeApproval\MonthClosureService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\PhoneNumberHelper;
use Illuminate\Support\Collection;

/**
 * Import-Kern der FRITZ!Box-Anrufliste: bucht Telefonate als Zeiteinträge.
 *
 * Entscheidungsbaum je Anruf ({@see bookCall}): unterdrückte, verpasste und
 * per Allowlist/Ignorierliste ausgeschlossene Anrufe fallen raus (ignored);
 * bereits importierte und Unter-Mindestdauer-Anrufe werden übersprungen
 * (skipped); geteilte oder unbekannte Nummern landen gruppiert in der
 * universellen Zuordnungs-Inbox (pending). Bei bekanntem Kunden/Endkunden
 * verschmilzt ein Anruf, der eine bestehende Zeit desselben Kunden überlappt
 * oder ihr ≤ Lead-Fenster vorausgeht, mit diesem Eintrag (linked, Start wird
 * vorgezogen — Muster RemoteSupport-Verbindungsversuche); sonst entsteht ein
 * eigener Eintrag (created). Monatsabschluss und exportierte Einträge werden
 * nie angefasst (locked bzw. Verknüpfung ohne Modifikation).
 *
 * Nummern-Gedächtnis über ExternalReference/-Alias (extref_unique erlaubt je
 * Ziel nur eine Primärreferenz je Typ — Mehrfachfälle laufen als Alias):
 * `number` → Customer|ForeignCustomer (gelernt), `shared_number`/
 * `ignored_number` → Organization.
 */
class FritzboxImportService {
    use PersistsTimeImportInbox;

    public const EXT_TYPE_CALL = 'call';

    /** MVP-534: verarbeitete Stempel-Anrufe (Idempotenz je Anruf-Schlüssel). */
    public const EXT_TYPE_STAMP = 'stamp';

    /** MVP-534: Rufnummer → Benutzer („die Rufnummer wirkt als Ausweis"). */
    public const EXT_TYPE_STAMP_NUMBER = 'stamp_number';

    public const EXT_TYPE_NUMBER = 'number';

    public const EXT_TYPE_SHARED = 'shared_number';

    public const EXT_TYPE_IGNORED = 'ignored_number';

    /** Vorschau-Zeilen je Inbox-Gruppe (Muster MatchingTimeImportService). */
    private const GROUP_PREVIEW_LIMIT = 15;

    protected function pluginId(): string {
        return FritzboxPlugin::ID;
    }

    protected function entryExternalType(): string {
        return self::EXT_TYPE_CALL;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{created: int, linked: int, skipped: int, ignored: int, pending: int, locked: int, stamped: int}
     *
     * @throws \RuntimeException wenn der Inhalt keine Anrufliste ist oder kein Benutzer buchbar ist
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        $calls = (new FritzboxCsvParser)->parse(
            $csvContent,
            $this->orgTimezone($organization),
            (bool) ($config['type3_outgoing'] ?? false),
        );

        return $this->importCalls($organization, $config, $calls);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  iterable<FritzboxCall>  $calls
     * @return array{created: int, linked: int, skipped: int, ignored: int, pending: int, locked: int, stamped: int}
     */
    public function importCalls(Organization $organization, array $config, iterable $calls): array {
        $userId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            throw new \RuntimeException((string) __('Kein buchbarer Benutzer in der Organisation.'));
        }

        $result = ['created' => 0, 'linked' => 0, 'skipped' => 0, 'ignored' => 0, 'pending' => 0, 'locked' => 0, 'stamped' => 0];
        $stampLines = $this->stampLines($config);
        foreach ($calls as $call) {
            // MVP-534: Anrufe auf eine Stempel-MSN sind Zeitstempel, keine
            // buchbaren Telefonate — sie durchlaufen NIE bookCall().
            $action = $stampLines[trim((string) $call->ownLine)] ?? null;
            if ($action !== null) {
                $result[$this->handleStampCall($organization, $call, $action)]++;

                continue;
            }
            $result[$this->bookCall($organization, $config, $call, $userId)]++;
        }

        return $result;
    }

    /**
     * Stempel-MSNs aus der Plugin-Konfiguration: eigene Rufnummer (wie in der
     * Anrufliste ausgewiesen) → Aktion. Leer = Telefonstempeln aus.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, 'in'|'out'|'toggle'>
     */
    private function stampLines(array $config): array {
        $lines = [];
        foreach (['in' => 'stamp_in_line', 'out' => 'stamp_out_line', 'toggle' => 'stamp_toggle_line'] as $action => $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '') {
                $lines[$value] = $action;
            }
        }

        return $lines;
    }

    /**
     * MVP-534 (Q1 S. 57): Telefonstempeln — der Anruf wird nicht angenommen,
     * die Rufnummer des Anrufenden wirkt als Ausweis. Unterdrückte oder nicht
     * zugeordnete Nummern werden ausgefiltert; die Zuordnung Rufnummer→User
     * pflegt der Admin ({@see rememberStampNumber}). Mindestdauer gilt hier
     * bewusst NICHT (auch verpasste/abgewiesene Anrufe stempeln).
     *
     * @param  'in'|'out'|'toggle'  $action
     * @return 'stamped'|'skipped'|'ignored'
     */
    private function handleStampCall(Organization $organization, FritzboxCall $call, string $action): string {
        if ($call->type === FritzboxCall::TYPE_OUTGOING) {
            return 'ignored';
        }
        $e164 = $call->e164;
        if ($e164 === null || $e164 === '') {
            return 'ignored'; // unterdrückte Nummer kann sich nicht ausweisen
        }
        // Aliasse zählen mit (extref_unique: nur eine Primärreferenz je Ziel —
        // Kommen UND Gehen desselben Tages zeigen auf dieselbe Attendance).
        $alreadyStamped = ExternalReference::forPlugin($organization->id, $this->pluginId(), self::EXT_TYPE_STAMP)
            ->forExternalId($call->callKey())->exists()
            || ExternalReferenceAlias::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', $this->pluginId())
                ->where('external_type', self::EXT_TYPE_STAMP)
                ->where('external_id', $call->callKey())
                ->exists();
        if ($alreadyStamped) {
            return 'skipped';
        }

        $reference = ExternalReference::forPlugin($organization->id, $this->pluginId(), self::EXT_TYPE_STAMP_NUMBER)
            ->forExternalId($e164)
            ->first();
        $user = $reference?->referenceable;
        if (! $user instanceof User || (int) $user->organization_id !== (int) $organization->id) {
            return 'ignored';
        }

        $clock = app(\App\Services\Attendance\AttendanceClockService::class);
        $resolved = $action === 'toggle'
            ? ($clock->current($user) !== null ? 'out' : 'in')
            : $action;

        try {
            if ($resolved === 'in') {
                $attendance = $clock->clockIn($user, [
                    'source' => \App\Enums\Attendance\AttendanceSource::Phone->value,
                    'started_at' => $call->startedAt->toIso8601String(),
                    'device' => 'phone:' . trim((string) $call->ownLine),
                ]);
            } else {
                $attendance = $clock->clockOut($user, [
                    'ended_at' => $call->startedAt->toIso8601String(),
                    'device' => 'phone:' . trim((string) $call->ownLine),
                ]);
                if ($attendance === null) {
                    return 'ignored'; // Gehen ohne offenes Kommen
                }
            }
        } catch (\Throwable) {
            return 'ignored'; // Doppel-Kommen, ungültige Zeit u. Ä.
        }

        $hasPrimary = ExternalReference::forPlugin($organization->id, $this->pluginId(), self::EXT_TYPE_STAMP)
            ->where('referenceable_type', $attendance->getMorphClass())
            ->where('referenceable_id', $attendance->getKey())
            ->exists();
        if ($hasPrimary) {
            // Zweiter Stempel derselben Anwesenheit (Gehen) → Alias-Zeile.
            ExternalReferenceAlias::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'external_type' => self::EXT_TYPE_STAMP,
                'external_id' => $call->callKey(),
                'referenceable_type' => $attendance->getMorphClass(),
                'referenceable_id' => $attendance->getKey(),
            ]);
        } else {
            ExternalReference::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'external_type' => self::EXT_TYPE_STAMP,
                'referenceable_type' => $attendance->getMorphClass(),
                'referenceable_id' => $attendance->getKey(),
                'external_id' => $call->callKey(),
                'payload' => ['action' => $resolved, 'own_line' => trim((string) $call->ownLine)],
                'synced_at' => CarbonImmutable::now(),
            ]);
        }

        return 'stamped';
    }

    /**
     * MVP-534: Stempel-Rufnummer einem Benutzer zuordnen (ersetzt eine
     * bestehende Zuordnung derselben Nummer).
     */
    public function rememberStampNumber(Organization $organization, string $e164, User $user): void {
        ExternalReference::forPlugin($organization->id, $this->pluginId(), self::EXT_TYPE_STAMP_NUMBER)
            ->forExternalId($e164)
            ->get()->each->delete();

        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => self::EXT_TYPE_STAMP_NUMBER,
            'referenceable_type' => $user->getMorphClass(),
            'referenceable_id' => $user->getKey(),
            'external_id' => $e164,
            'synced_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return 'created'|'linked'|'skipped'|'ignored'|'pending'|'locked'
     */
    public function bookCall(Organization $organization, array $config, FritzboxCall $call, int $userId): string {
        // Unterdrückte/unparsbare Nummern und erfolglose Anrufe (verpasst,
        // abgewiesen, Dauer 0) sind nie buchbar — ohne Referenz, damit der
        // Reimport billig deterministisch bleibt.
        if ($call->e164 === null || $call->isMissed()) {
            return 'ignored';
        }

        /** @var list<string> $allowlist */
        $allowlist = $config['own_number_allowlist'] ?? [];
        if ($allowlist !== [] && ! in_array((string) $call->ownLine, $allowlist, true)) {
            return 'ignored';
        }

        if ($this->isIgnored($organization, $call->e164)) {
            return 'ignored';
        }

        if ($this->alreadyImported($organization, $call->callKey())) {
            return 'skipped';
        }

        // Unter Mindestdauer: keine Referenz — eine Senkung des Settings holt
        // die Anrufe beim nächsten Reimport nach.
        if ($call->durationMinutes < (int) ($config['min_call_minutes'] ?? 2)) {
            return 'skipped';
        }

        // Geteilte Nummern (Dienstleister-Hotlines im Kundenauftrag) werden
        // IMMER einzeln zugeordnet — auch wenn zusätzlich eine gelernte
        // Zuordnung existiert.
        if ($this->isShared($organization, $call->e164)) {
            $this->recordPendingCall($organization, $call, sharedSingle: true);

            return 'pending';
        }

        $target = $this->matchTarget($organization, $call->e164);
        if ($target === null) {
            $this->recordPendingCall($organization, $call);

            return 'pending';
        }

        [$status] = $this->bookForTarget($organization, $config, $call, $target, $userId);

        return $status;
    }

    /**
     * Bucht einen Anruf auf ein bekanntes Ziel: verschmelzen mit bestehender
     * Zeit desselben Kunden oder eigener Eintrag.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: 'created'|'linked'|'locked', 1: ?TimeEntry}
     */
    public function bookForTarget(Organization $organization, array $config, FritzboxCall $call, Customer|ForeignCustomer $target, int $userId): array {
        $customerId = $target instanceof ForeignCustomer ? (int) $target->customer_id : (int) $target->getKey();

        $covering = $this->findCoveringEntry($organization, $call, $userId, $customerId, (int) ($config['call_lead_minutes'] ?? 15));
        if ($covering !== null) {
            $this->linkToEntry($organization, $call, $covering);

            return ['linked', $covering];
        }

        $user = User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($userId);
        if ($user instanceof User && app(MonthClosureService::class)->isPeriodLockedForUser($user, $this->localDay($organization, $call->startedAt))) {
            return ['locked', null];
        }

        return ['created', $this->createOwnEntry($organization, $call, $target, $userId, (bool) ($config['default_billable'] ?? true))];
    }

    // ── Nummern-Matching ─────────────────────────────────────────────────────

    /**
     * Rufnummer → Kunde/Endkunde: gelernte Referenz (Primär oder Alias) vor
     * Stammdaten-Match (tail-7-LIKE-Vorfilter auf phone/mobile, dann exakter
     * E.164-Vergleich — Muster {@see \App\Services\Cti\CtiCallService}).
     * Endkunden-Treffer gewinnen: das präzisere Buchungsziel.
     */
    public function matchTarget(Organization $organization, string $e164): Customer|ForeignCustomer|null {
        $ref = ExternalReference::query()
            ->forPlugin($organization, FritzboxPlugin::ID, self::EXT_TYPE_NUMBER)
            ->forExternalId($e164)
            ->first();
        $model = $ref?->referenceable;
        if ($model instanceof Customer || $model instanceof ForeignCustomer) {
            return $model;
        }

        $model = ExternalReferenceAlias::resolveModel((int) $organization->id, FritzboxPlugin::ID, self::EXT_TYPE_NUMBER, $e164);
        if ($model instanceof Customer || $model instanceof ForeignCustomer) {
            return $model;
        }

        $tail = substr((string) preg_replace('/\D/', '', $e164), -7);
        if ($tail === '') {
            return null;
        }

        foreach ([ForeignCustomer::class, Customer::class] as $class) {
            $candidates = $class::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where(function ($query) use ($tail): void {
                    $query->whereLikeEscaped('phone', $tail)
                        ->orWhereLikeEscaped('mobile', $tail);
                })
                ->limit(50)
                ->get();

            foreach ($candidates as $candidate) {
                foreach ([(string) $candidate->phone, (string) $candidate->mobile] as $stored) {
                    if ($stored !== '' && PhoneNumberHelper::toE164($stored, 'DE') === $e164) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    // ── Nummern-Gedächtnis ───────────────────────────────────────────────────

    /** Merkt die Nummer dauerhaft am Ziel; eine bestehende Zuordnung wird überschrieben. */
    public function rememberNumber(Organization $organization, string $e164, Customer|ForeignCustomer $target): void {
        $primary = ExternalReference::query()
            ->forPlugin($organization, FritzboxPlugin::ID, self::EXT_TYPE_NUMBER)
            ->forExternalId($e164)
            ->first();
        if ($primary !== null && ! ($primary->referenceable_type === $target->getMorphClass() && (int) $primary->referenceable_id === (int) $target->getKey())) {
            $primary->delete(); // Umzuordnung: alte Primärreferenz weicht
            $primary = null;
        }

        ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', FritzboxPlugin::ID)
            ->where('external_type', self::EXT_TYPE_NUMBER)
            ->where('external_id', $e164)
            ->delete();

        if ($primary !== null) {
            return; // bereits korrekt gemerkt
        }

        $this->rememberReference($organization, self::EXT_TYPE_NUMBER, $e164, $target, ['learned_from' => 'inbox']);
    }

    /**
     * Markiert die Nummer als geteilt (Einzelzuordnung je Anruf) und splittet
     * offene Inbox-Items der Nummer in Einzelgruppen.
     */
    public function markShared(Organization $organization, string $e164): void {
        $this->rememberReference($organization, self::EXT_TYPE_SHARED, $e164, $organization);

        foreach ($this->openItemsOfNumber($organization, $e164, includeSingles: false)->get() as $item) {
            $item->update(['group_key' => $e164 . '|' . $item->external_id]);
        }
    }

    /** Ignoriert die Nummer dauerhaft (privat/Spam) und verwirft offene Items. */
    public function markIgnored(Organization $organization, string $e164): int {
        $this->rememberReference($organization, self::EXT_TYPE_IGNORED, $e164, $organization);

        return $this->openItemsOfNumber($organization, $e164, includeSingles: true)
            ->update(['status' => IntegrationInboxItem::STATUS_DISMISSED]);
    }

    public function isShared(Organization $organization, string $e164): bool {
        return $this->hasOrgFlag($organization, self::EXT_TYPE_SHARED, $e164);
    }

    public function isIgnored(Organization $organization, string $e164): bool {
        return $this->hasOrgFlag($organization, self::EXT_TYPE_IGNORED, $e164);
    }

    // ── Inbox (vom GroupBooker genutzt) ──────────────────────────────────────

    /**
     * Offene Anruf-Gruppen: je unbekannter Nummer eine Gruppe; geteilte
     * Nummern liegen als Einzelgruppen (`e164|callKey`) mit `shared: true`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openInboxGroups(Organization $organization): Collection {
        return $this->openInboxItems($organization)
            ->groupBy('group_key')
            ->map(function (Collection $items, string $groupKey): array {
                $snapshots = $items->map(fn (IntegrationInboxItem $item): array => (array) $item->remote_snapshot);
                $first = (array) $snapshots->first();
                $e164 = (string) ($first['e164'] ?? (str_contains($groupKey, '|') ? explode('|', $groupKey)[0] : $groupKey));
                $name = $snapshots->pluck('name')->filter()->countBy()->sortDesc()->keys()->first();

                $starts = $snapshots->map(fn (array $snap): CarbonImmutable => CarbonImmutable::parse((string) $snap['started_at']));

                /** @var array<string, mixed> $out */
                $out = [
                    'group_key' => $groupKey,
                    'plugin_id' => FritzboxPlugin::ID,
                    'e164' => $e164,
                    'number' => PhoneNumberHelper::format($e164, 'international', 'DE'),
                    'name' => $name !== null ? (string) $name : null,
                    'shared' => str_contains($groupKey, '|'),
                    'count' => $items->count(),
                    'minutes' => (int) $snapshots->sum(fn (array $snap): int => (int) ($snap['duration_minutes'] ?? 0)),
                    'first_seen' => $starts->min(),
                    'last_seen' => $starts->max(),
                    'entries' => $snapshots
                        ->sortByDesc('started_at')
                        ->take(self::GROUP_PREVIEW_LIMIT)
                        ->map(fn (array $snap): array => [
                            'started_at' => (string) ($snap['started_at'] ?? ''),
                            'ended_at' => (string) ($snap['ended_at'] ?? ''),
                            'minutes' => (int) ($snap['duration_minutes'] ?? 0),
                            'description' => $this->callLabel($snap),
                        ])
                        ->values()
                        ->all(),
                ];

                return $out;
            })
            ->values();
    }

    /**
     * Bucht alle offenen Anrufe einer Gruppe auf das gewählte Ziel.
     *
     * @param  array<string, mixed>  $config
     * @return array{created: int, linked: int, skipped: int, locked: int}
     */
    public function assignGroup(Organization $organization, string $groupKey, Customer|ForeignCustomer $target, bool $remember, array $config, ?int $userId = null): array {
        $userId ??= $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            throw new \RuntimeException((string) __('Kein buchbarer Benutzer in der Organisation.'));
        }

        $result = ['created' => 0, 'linked' => 0, 'skipped' => 0, 'locked' => 0];
        $items = $this->openInboxItems($organization)->where('group_key', $groupKey);

        foreach ($items as $item) {
            $call = $this->callFromSnapshot((array) $item->remote_snapshot);
            if ($call === null || $this->alreadyImported($organization, $call->callKey())) {
                $result['skipped']++;
                $this->resolveItem($item, IntegrationInboxItem::STATUS_DISMISSED, null);

                continue;
            }

            [$status, $entry] = $this->bookForTarget($organization, $config, $call, $target, $userId);
            $result[$status]++;
            if ($status === 'locked') {
                continue; // Item bleibt offen — nach dem Entsperren erneut buchbar
            }

            $this->resolveItem(
                $item,
                $status === 'created' ? IntegrationInboxItem::STATUS_RESOLVED_CREATED : IntegrationInboxItem::STATUS_RESOLVED_LINKED,
                $entry,
            );
        }

        // Merken nur für Nummern-Gruppen — Einzelzuordnungen geteilter
        // Nummern lernen bewusst nichts.
        if ($remember && ! str_contains($groupKey, '|')) {
            $this->rememberNumber($organization, $groupKey, $target);
        }

        return $result;
    }

    public function dismissGroup(Organization $organization, string $groupKey): int {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', FritzboxPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->where('group_key', $groupKey)
            ->update(['status' => IntegrationInboxItem::STATUS_DISMISSED]);
    }

    // ── Korrelation ──────────────────────────────────────────────────────────

    /**
     * Bestehender Zeiteintrag desselben Benutzers UND Kunden, den der Anruf
     * überlappt — bei mehreren gewinnt die größte Überlappung. Ohne echte
     * Überlappung zählt der nächstgelegene Eintrag, der höchstens
     * `$leadMinutes` nach Anrufende beginnt (Anruf → Sitzungsaufbau).
     */
    private function findCoveringEntry(Organization $organization, FritzboxCall $call, int $userId, int $customerId, int $leadMinutes): ?TimeEntry {
        $candidates = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('started_at', '<=', $call->endedAt->addMinutes($leadMinutes))
            ->where('ended_at', '>', $call->startedAt)
            ->whereIn('project_id', Project::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $customerId)
                ->select('id'))
            ->get();

        $best = null;
        $bestOverlap = 0;
        $nearest = null;
        $nearestGap = PHP_INT_MAX;
        foreach ($candidates as $entry) {
            if ($entry->started_at === null || $entry->ended_at === null) {
                continue;
            }
            $overlap = min($entry->ended_at->getTimestamp(), $call->endedAt->getTimestamp())
                - max($entry->started_at->getTimestamp(), $call->startedAt->getTimestamp());
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $best = $entry;

                continue;
            }
            $gap = $entry->started_at->getTimestamp() - $call->endedAt->getTimestamp();
            if ($overlap <= 0 && $gap >= 0 && $gap <= $leadMinutes * 60 && $gap < $nearestGap) {
                $nearestGap = $gap;
                $nearest = $entry;
            }
        }

        // Echte Überlappung schlägt das Lead-Fenster.
        return $best ?? $nearest;
    }

    /**
     * Verknüpft den Anruf als Nachweis mit dem bestehenden Eintrag und zieht
     * dessen Start auf den Anrufbeginn vor (Telefonzeit wird mit abgerechnet).
     * Exportierte Einträge und abgeschlossene Monate werden nie modifiziert —
     * die Verknüpfung verhindert trotzdem die Doppelbuchung.
     */
    private function linkToEntry(Organization $organization, FritzboxCall $call, TimeEntry $entry): void {
        $this->rememberCallReference($organization, $call, $entry, linked: true);

        if ($entry->started_at === null || $call->startedAt->getTimestamp() >= $entry->started_at->getTimestamp()) {
            return;
        }
        if ((bool) $entry->exported) {
            return;
        }

        $user = User::query()->withoutGlobalScopes()->find($entry->user_id);
        if ($user instanceof User) {
            $closure = app(MonthClosureService::class);
            $lockedNew = $closure->isPeriodLockedForUser($user, $this->localDay($organization, $call->startedAt));
            $lockedOld = $closure->isPeriodLockedForUser($user, $this->localDay($organization, CarbonImmutable::instance($entry->started_at)));
            if ($lockedNew || $lockedOld) {
                return;
            }
        }

        $entry->started_at = \Illuminate\Support\Carbon::instance($call->startedAt);
        // Der saving-Hook setzt `date` nur, wenn leer — beim Vorziehen über
        // Mitternacht muss der lokale Kalendertag explizit mitziehen.
        $entry->date = \Illuminate\Support\Carbon::instance($this->localDay($organization, $call->startedAt)->startOfDay());
        $entry->save();
    }

    private function createOwnEntry(Organization $organization, FritzboxCall $call, Customer|ForeignCustomer $target, int $userId, bool $billable): TimeEntry {
        $project = $target->defaultProjectOrCreate();

        $entry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $this->localDay($organization, $call->startedAt)->startOfDay(),
            'started_at' => $call->startedAt,
            'ended_at' => $call->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $this->callLabel([
                'direction' => $call->direction,
                'name' => $call->name,
                'e164' => $call->e164,
                'number_raw' => $call->numberRaw,
            ]),
            'billable' => $billable,
        ]);

        $this->rememberCallReference($organization, $call, $entry, linked: false);

        return $entry;
    }

    // ── Persistenz-Helfer ────────────────────────────────────────────────────

    /** Idempotenz-Anker: verknüpft den Anruf mit dem Eintrag (Alias bei besetzter Primärreferenz). */
    private function rememberCallReference(Organization $organization, FritzboxCall $call, TimeEntry $entry, bool $linked): void {
        $this->rememberReference($organization, self::EXT_TYPE_CALL, $call->callKey(), $entry, [
            'e164' => $call->e164,
            'direction' => $call->direction,
            'duration_minutes' => $call->durationMinutes,
            // true = Anruf nur als Nachweis an vorhandene Zeit gekoppelt.
            'linked' => $linked,
        ]);
    }

    /**
     * Primär-oder-Alias-Upsert (Muster RemoteSupport::rememberSessionReference):
     * extref_unique erlaubt je Ziel nur EINE Primärreferenz je Typ — weitere
     * external_ids am selben Ziel laufen als Alias; alreadyImported()/
     * hasOrgFlag() kennen beide.
     *
     * @param  array<string, mixed>  $payload
     */
    private function rememberReference(Organization $organization, string $externalType, string $externalId, \Illuminate\Database\Eloquent\Model $target, array $payload = []): void {
        $occupied = ExternalReference::query()
            ->forPlugin($organization, FritzboxPlugin::ID, $externalType)
            ->forReferenceable($target)
            ->exists();

        if ($occupied) {
            ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'plugin_id' => FritzboxPlugin::ID,
                    'external_type' => $externalType,
                    'external_id' => $externalId,
                ],
                [
                    'referenceable_type' => $target->getMorphClass(),
                    'referenceable_id' => $target->getKey(),
                ],
            );

            return;
        }

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => FritzboxPlugin::ID,
            'external_type' => $externalType,
            'referenceable_type' => $target->getMorphClass(),
            'referenceable_id' => $target->getKey(),
            'external_id' => $externalId,
            'payload' => $payload === [] ? null : $payload,
            'synced_at' => now(),
        ]);
    }

    private function hasOrgFlag(Organization $organization, string $externalType, string $e164): bool {
        return ExternalReference::query()
            ->forPlugin($organization, FritzboxPlugin::ID, $externalType)
            ->forExternalId($e164)
            ->exists()
            || ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', FritzboxPlugin::ID)
                ->where('external_type', $externalType)
                ->where('external_id', $e164)
                ->exists();
    }

    private function recordPendingCall(Organization $organization, FritzboxCall $call, bool $sharedSingle = false): void {
        $e164 = (string) $call->e164;

        $this->recordPendingItem($organization, $call->callKey(), [
            'source' => 'csv',
            'group_key' => $sharedSingle ? $e164 . '|' . $call->callKey() : $e164,
            'remote_snapshot' => [
                'type' => $call->type,
                'direction' => $call->direction,
                'started_at' => $call->startedAt->toIso8601String(),
                'ended_at' => $call->endedAt->toIso8601String(),
                'duration_minutes' => $call->durationMinutes,
                'number_raw' => $call->numberRaw,
                'e164' => $e164,
                'name' => $call->name,
                'own_line' => $call->ownLine,
            ],
            'display_title' => trim(PhoneNumberHelper::format($e164, 'international', 'DE') . ($call->name !== null ? ' — ' . $call->name : '')),
            'display_subtitle' => $this->callLabel([
                'direction' => $call->direction,
                'name' => null,
                'e164' => null,
                'number_raw' => null,
            ]) . ', ' . $call->durationMinutes . ' min',
            'occurred_at' => $call->startedAt,
        ]);
    }

    /** @param array<string, mixed> $snap */
    private function callFromSnapshot(array $snap): ?FritzboxCall {
        try {
            $startedAt = CarbonImmutable::parse((string) ($snap['started_at'] ?? ''))->utc();
            $endedAt = CarbonImmutable::parse((string) ($snap['ended_at'] ?? ''))->utc();
        } catch (\Throwable) {
            return null;
        }

        return new FritzboxCall(
            type: (int) ($snap['type'] ?? 0),
            direction: (string) ($snap['direction'] ?? FritzboxCall::DIR_IN),
            startedAt: $startedAt,
            endedAt: $endedAt,
            durationMinutes: (int) ($snap['duration_minutes'] ?? 0),
            numberRaw: (string) ($snap['number_raw'] ?? ''),
            e164: isset($snap['e164']) && $snap['e164'] !== '' ? (string) $snap['e164'] : null,
            name: isset($snap['name']) && $snap['name'] !== '' ? (string) $snap['name'] : null,
            ownLine: isset($snap['own_line']) && $snap['own_line'] !== '' ? (string) $snap['own_line'] : null,
        );
    }

    /** @param array<string, mixed> $snap */
    private function callLabel(array $snap): string {
        $direction = ($snap['direction'] ?? FritzboxCall::DIR_IN) === FritzboxCall::DIR_OUT
            ? (string) __('Telefonat (ausgehend)')
            : (string) __('Telefonat (eingehend)');

        $who = (string) (($snap['name'] ?? null) ?: ($snap['e164'] ?? null) ?: ($snap['number_raw'] ?? null) ?: '');

        return $who !== '' ? $direction . ' — ' . $who : $direction;
    }

    /**
     * Offene Inbox-Items einer Nummer (optional inkl. geteilter Einzelgruppen).
     *
     * @return \Illuminate\Database\Eloquent\Builder<IntegrationInboxItem>
     */
    private function openItemsOfNumber(Organization $organization, string $e164, bool $includeSingles): \Illuminate\Database\Eloquent\Builder {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', FritzboxPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->where(function ($query) use ($e164, $includeSingles): void {
                $query->where('group_key', $e164);
                if ($includeSingles) {
                    $query->orWhereLikeEscaped('group_key', $e164 . '|', 'prefix');
                }
            });
    }

    private function orgTimezone(Organization $organization): string {
        return Tz::isValid($organization->timezone) ? (string) $organization->timezone : Tz::FALLBACK;
    }

    /** Lokaler Kalendertag (Org-Zeitzone) eines UTC-Zeitpunkts — für Monatsabschluss und `date`. */
    private function localDay(Organization $organization, CarbonImmutable $utc): CarbonImmutable {
        return $utc->setTimezone($this->orgTimezone($organization));
    }
}
