<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarImportStager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use App\Enums\Integration\{DataDomain, IntegrationInboxStatus};
use App\Models\Calendar\Event;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Services\Integration\Attributes\WritesDataDomain;
use App\Services\Integration\Contracts\OwnershipConflictReplay;
use App\Services\Integration\{DataOwnershipGuard, InboxActionService};
use App\Services\Integration\Dto\OwnershipClaim;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Inbox-Fälle des Kalender-Rückimports — der gemeinsame Kern der drei
 * Anbieter (Konsolidierungs-Audit 2026-10, k2-05).
 *
 * Serientermine (MVP-977): Jedes Vorkommen im
 * Importfenster wird ein eigener Vorschlag (`calendar-proposal:<uid>:<Beginn>`),
 * abweichende und abgesagte Einzeltermine sind damit richtig. Die Inbox bucht
 * oder verwirft eine Serie als Gruppe ({@see CalendarSeriesGroupBooker}).
 *
 * Datenführerschaft (MVP-1592): Führt ein anderes System den Kalender, wird
 * aus Vorschlag, Konflikt oder Serie ein Führerschaftskonflikt; „übernehmen“
 * legt den Termin bzw. die Serie einmal an. Lösch-Hinweise bleiben, sie
 * schreiben nichts.
 */
#[WritesDataDomain(DataDomain::Calendar)]
final class CalendarImportStager implements OwnershipConflictReplay {
    /** Obergrenze je Serie, damit eine tägliche Serie die Inbox nicht flutet. */
    public const int MAX_OCCURRENCES = 60;

    /** Lösch-Hinweis eines veröffentlichten Termins: nur Kenntnisnahme, kein Schreiben. */
    public const string DELETED_PREFIX = 'calendar-deleted:';

    public const string OWNERSHIP_CASE = 'calendar.case';

    public const string OWNERSHIP_SERIES = 'calendar.series';

    public const string OWNERSHIP_OCCURRENCE = 'calendar.occurrence';

    /** @var array<int, Organization|null> */
    private array $organizations = [];

    /** Toleranz zwischen unserem Schreibvorgang und dem Änderungsstempel der Gegenseite. */
    private const int ECHO_TOLERANCE_SECONDS = 120;

    /**
     * Unsere eigenen Schreibvorgänge erscheinen in der Änderungsliste der
     * Gegenseite ebenfalls: ohne Abgleichsstempel oder innerhalb der Toleranz
     * danach ist die Änderung kein fremder Eingriff.
     */
    public static function isOwnEcho(DateTimeInterface $modified, ?DateTimeInterface $syncedAt): bool {
        return $syncedAt === null
            || (float) $modified->format('U.u') <= (float) $syncedAt->format('U.u') + self::ECHO_TOLERANCE_SECONDS;
    }

    /**
     * Einzelfall (Vorschlag, Konflikt, Lösch-Hinweis) einmal je Schlüssel anlegen.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $mapped
     * @return bool true = neuer Fall
     */
    public function stageCase(int $organizationId, string $pluginId, string $subtitle, string $dedupeKey, string $caseType, array $snapshot, string $title, ?ExternalReference $reference, ?array $mapped = null): bool {
        if (! str_starts_with($dedupeKey, self::DELETED_PREFIX) && ! $this->permits($organizationId, $pluginId, self::OWNERSHIP_CASE, $dedupeKey, $title, $subtitle, [
            'dedupe_key' => $dedupeKey,
            'case_type' => $caseType,
            'snapshot' => $snapshot,
            'title' => $title,
            'subtitle' => $subtitle,
            'reference_id' => $reference?->getKey(),
            'mapped' => $mapped,
        ], (string) ($snapshot['remote_id'] ?? ''), $reference?->referenceable)) {
            return false;
        }

        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $organizationId,
            'plugin_id' => $pluginId,
            'dedupe_key' => $dedupeKey,
        ], [
            'source' => $pluginId,
            'target_type' => (new Event)->getMorphClass(),
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => (string) ($snapshot['remote_id'] ?? ''),
            'case_type' => $caseType,
            'status' => IntegrationInboxStatus::Open,
            'referenceable_type' => $reference?->referenceable_type,
            'referenceable_id' => $reference?->referenceable_id,
            'remote_snapshot' => $snapshot,
            'mapped_snapshot' => $mapped,
            'display_title' => $title !== '' ? $title : '—',
            'display_subtitle' => $subtitle,
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }

    /** Vorkommen über den ursprünglichen Beginn oder — wo der Anbieter sie vergibt — die eigene ID des Vorkommens. */
    public static function key(string $uid, DateTimeInterface|string $occurrence): string {
        return 'calendar-proposal:' . $uid . ':' . ($occurrence instanceof DateTimeInterface ? (string) $occurrence->getTimestamp() : $occurrence);
    }

    /**
     * Legt die Vorkommen einer Serie an und verwirft offene Vorschläge der
     * Serie, die es nicht mehr gibt (abgesagt, Serie gekürzt).
     *
     * @param  list<array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}>  $occurrences
     * @return int neue Vorschläge
     */
    public function stageSeries(int $organizationId, string $pluginId, string $uid, string $subtitle, array $occurrences): int {
        $occurrences = array_slice($occurrences, 0, self::MAX_OCCURRENCES);
        if (! $this->permits($organizationId, $pluginId, self::OWNERSHIP_SERIES, 'calendar-series:' . $uid, (string) ($occurrences[0]['title'] ?? ''), $subtitle, [
            'uid' => $uid,
            'subtitle' => $subtitle,
            'occurrences' => $occurrences,
        ], $uid)) {
            return 0;
        }

        $new = 0;
        $keys = [];
        foreach ($occurrences as $occurrence) {
            $keys[] = $occurrence['key'];
            $item = IntegrationInboxItem::query()->firstOrCreate(
                ['organization_id' => $organizationId, 'plugin_id' => $pluginId, 'dedupe_key' => $occurrence['key']],
                $this->attributes($pluginId, $subtitle, $occurrence),
            );
            $new += $item->wasRecentlyCreated ? 1 : 0;
        }

        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('status', IntegrationInboxStatus::Open)
            ->whereLikeEscaped('dedupe_key', 'calendar-proposal:' . $uid . ':', 'prefix')
            ->whereNotIn('dedupe_key', $keys)
            ->update(['status' => IntegrationInboxStatus::Dismissed, 'resolved_at' => now()]);

        return $new;
    }

    /**
     * Abweichender Einzeltermin, der ohne seine Serie kommt: offenen Vorschlag
     * aktualisieren oder neu anlegen.
     *
     * @param  array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}  $occurrence
     * @return bool true = neuer Vorschlag
     */
    public function upsertOccurrence(int $organizationId, string $pluginId, string $subtitle, array $occurrence): bool {
        if (! $this->permits($organizationId, $pluginId, self::OWNERSHIP_OCCURRENCE, $occurrence['key'], $occurrence['title'], $subtitle, [
            'subtitle' => $subtitle,
            'occurrence' => $occurrence,
        ], substr($occurrence['key'], strlen('calendar-proposal:')))) {
            return false;
        }

        $item = IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $occurrence['key'])
            ->first();
        if ($item === null) {
            IntegrationInboxItem::query()->create(['organization_id' => $organizationId, 'plugin_id' => $pluginId, 'dedupe_key' => $occurrence['key']] + $this->attributes($pluginId, $subtitle, $occurrence));

            return true;
        }
        if ($item->status === IntegrationInboxStatus::Open) {
            $item->update([
                'remote_snapshot' => $occurrence['snapshot'],
                'mapped_snapshot' => $occurrence['mapped'],
                'display_title' => $occurrence['title'] !== '' ? $occurrence['title'] : '—',
            ]);
        }

        return false;
    }

    /** Gelöschtes Vorkommen, von dem nur die eigene ID bekannt ist (Graph `@removed`). */
    public function dismissByRemoteId(int $organizationId, string $pluginId, string $remoteId): void {
        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('status', IntegrationInboxStatus::Open)
            ->whereLikeEscaped('dedupe_key', ':' . $remoteId, 'suffix')
            ->update(['status' => IntegrationInboxStatus::Dismissed, 'resolved_at' => now()]);
    }

    /**
     * Fremder Termin gelöscht: sein Einzelvorschlag und alle offenen Vorkommen
     * seiner Serie entfallen — sonst böte die Inbox „Neu anlegen“ für Termine
     * an, die es nicht mehr gibt. Trägt der Schlüssel der Serie die UID statt
     * der Remote-ID (CalDAV), trifft der Objektname im Snapshot.
     */
    public function dismissRemote(int $organizationId, string $pluginId, string $remoteId): void {
        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('status', IntegrationInboxStatus::Open)
            ->whereLikeEscaped('dedupe_key', 'calendar-proposal:', 'prefix')
            ->where(fn ($query) => $query
                ->where('dedupe_key', 'calendar-proposal:' . $remoteId)
                ->orWhere(fn ($series) => $series->whereLikeEscaped('dedupe_key', 'calendar-proposal:' . $remoteId . ':', 'prefix'))
                ->orWhere('remote_snapshot->object_name', $remoteId))
            ->update(['status' => IntegrationInboxStatus::Dismissed, 'resolved_at' => now()]);
    }

    /** Abgesagtes Vorkommen: ein offener Vorschlag entfällt. */
    public function dismiss(int $organizationId, string $pluginId, string $key): void {
        IntegrationInboxItem::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $key)
            ->where('status', IntegrationInboxStatus::Open)
            ->update(['status' => IntegrationInboxStatus::Dismissed, 'resolved_at' => now()]);
    }

    /** @return list<string> */
    public function ownershipKinds(): array {
        return [self::OWNERSHIP_CASE, self::OWNERSHIP_SERIES, self::OWNERSHIP_OCCURRENCE];
    }

    /**
     * Übernehmen: den Fall wie bei erlaubter Führung anlegen und sofort
     * buchen — Vorschlag und Serie als Termine, Konflikt mit dem Fremdstand.
     */
    public function replayOwnershipConflict(Organization $organization, IntegrationInboxItem $item): ?Model {
        $payload = (array) (($item->remote_snapshot ?? [])['payload'] ?? []);
        $orgId = (int) $organization->id;
        $pluginId = $item->plugin_id;
        $subtitle = (string) ($payload['subtitle'] ?? '');

        return match ($item->ownershipMarker()['kind'] ?? null) {
            self::OWNERSHIP_CASE => $this->replayCase($orgId, $pluginId, $subtitle, $payload),
            self::OWNERSHIP_SERIES => $this->replaySeries($orgId, $pluginId, $subtitle, $payload),
            self::OWNERSHIP_OCCURRENCE => $this->replayOccurrence($orgId, $pluginId, $subtitle, (array) ($payload['occurrence'] ?? [])),
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function replayCase(int $orgId, string $pluginId, string $subtitle, array $payload): ?Model {
        $key = (string) ($payload['dedupe_key'] ?? '');
        $caseType = (string) ($payload['case_type'] ?? IntegrationInboxItem::CASE_UNMATCHED);
        $reference = isset($payload['reference_id'])
            ? ExternalReference::query()->withoutGlobalScopes()->where('organization_id', $orgId)->find((int) $payload['reference_id'])
            : null;
        $this->stageCase($orgId, $pluginId, $subtitle, $key, $caseType, (array) ($payload['snapshot'] ?? []), (string) ($payload['title'] ?? ''), $reference, is_array($payload['mapped'] ?? null) ? $payload['mapped'] : null);

        $staged = $this->openCase($orgId, $pluginId, $key);
        if ($staged === null) {
            return null;
        }
        if ($caseType === IntegrationInboxItem::CASE_CONFLICT) {
            app(InboxActionService::class)->acceptRemote($staged);

            return $staged->referenceable;
        }

        return app(InboxActionService::class)->createFromItem($staged);
    }

    /** @param array<string, mixed> $payload */
    private function replaySeries(int $orgId, string $pluginId, string $subtitle, array $payload): ?Model {
        // Gespeicherter Stand aus JSON: nur vollständige Vorkommen abspielen.
        /** @var list<array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}> $occurrences */
        $occurrences = array_values(array_filter(
            (array) ($payload['occurrences'] ?? []),
            static fn (mixed $o): bool => is_array($o) && is_string($o['key'] ?? null) && is_string($o['title'] ?? null) && is_array($o['snapshot'] ?? null) && is_array($o['mapped'] ?? null),
        ));
        $this->stageSeries($orgId, $pluginId, (string) ($payload['uid'] ?? ''), $subtitle, $occurrences);

        $first = null;
        foreach ($occurrences as $occurrence) {
            $staged = $this->openCase($orgId, $pluginId, $occurrence['key']);
            if ($staged !== null) {
                $event = app(InboxActionService::class)->createFromItem($staged);
                $first ??= $event;
            }
        }

        return $first;
    }

    /** @param array<string, mixed> $occurrence */
    private function replayOccurrence(int $orgId, string $pluginId, string $subtitle, array $occurrence): ?Model {
        if (! isset($occurrence['key'], $occurrence['title'], $occurrence['snapshot'], $occurrence['mapped'])) {
            return null;
        }
        /** @var array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>} $occurrence */
        $this->upsertOccurrence($orgId, $pluginId, $subtitle, $occurrence);
        $staged = $this->openCase($orgId, $pluginId, $occurrence['key']);

        return $staged !== null ? app(InboxActionService::class)->createFromItem($staged) : null;
    }

    private function openCase(int $orgId, string $pluginId, string $dedupeKey): ?IntegrationInboxItem {
        return IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $dedupeKey)
            ->where('status', IntegrationInboxStatus::Open)
            ->first();
    }

    /**
     * Darf das Kalender-Plugin schreiben? Sonst Führerschaftskonflikt mit
     * allem, was das Übernehmen zum Anlegen braucht.
     *
     * @param  array<string, mixed>  $payload
     */
    private function permits(int $organizationId, string $pluginId, string $kind, string $key, string $title, string $subtitle, array $payload, string $externalId, ?Model $local = null): bool {
        $organization = $this->organizations[$organizationId] ??= Organization::query()->withoutGlobalScopes()->find($organizationId);
        if (! $organization instanceof Organization) {
            return false;
        }

        return app(DataOwnershipGuard::class)->allows($organization, DataDomain::Calendar, $pluginId, new OwnershipClaim(
            kind: $kind,
            key: $key,
            title: $title,
            target: Event::class,
            payload: $payload,
            externalId: $externalId !== '' ? $externalId : null,
            local: $local,
            subtitle: $subtitle !== '' ? $subtitle : null,
        ));
    }

    /**
     * @param  array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}  $occurrence
     * @return array<string, mixed>
     */
    private function attributes(string $pluginId, string $subtitle, array $occurrence): array {
        return [
            'source' => $pluginId,
            'target_type' => (new Event)->getMorphClass(),
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            // Je Vorkommen eigene Fremd-ID: eine angenommene Instanz bindet genau einen Termin.
            'external_id' => substr($occurrence['key'], strlen('calendar-proposal:')),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxStatus::Open,
            'remote_snapshot' => $occurrence['snapshot'],
            'mapped_snapshot' => $occurrence['mapped'],
            'display_title' => $occurrence['title'] !== '' ? $occurrence['title'] : '—',
            'display_subtitle' => $subtitle,
            'occurred_at' => now(),
        ];
    }
}
