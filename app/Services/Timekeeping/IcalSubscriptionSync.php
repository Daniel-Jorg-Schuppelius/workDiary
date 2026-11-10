<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSubscriptionSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timekeeping;

use App\Enums\Import\IcalSyncStatus;
use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Models\Time\{IcalSubscription, IcalSubscriptionItem};
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Import\{CalendarImportFeeds, EntitySpecRegistry, ImportOutcome, InboxFirstSpec};
use App\Services\Import\Source\Ical\IcalEvent;
use App\Services\Import\Source\ImportSourceFactory;
use App\Services\Timekeeping\Import\ProjectTimeSpec;
use App\Support\{CarbonFmt, OrganizationContext, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Facades\{Cache, Log};
use Throwable;

/**
 * Dauer-Sync abonnierter iCal-Quellen (Feature 094, MVP-1516). Jeder Termin
 * läuft durch dieselbe Import-Spezifikation wie der Einmalimport — gleiche
 * Zuordnung, Sperrschutz und Inbox-First, gleiche Fremd-ID (UID).
 *
 * - Inkrementell: unveränderte SEQUENCE und LAST-MODIFIED überspringen den
 *   Termin; fehlen sie, entscheidet der Abdruck der Zeile.
 * - Gelesen werden nur vergangene Termine im Rückblick; Serien löst das
 *   common-toolkit im selben Zeitraum auf.
 * - Bucht ein Einmalimport dieselbe UID bereits, übernimmt das Abo den Termin
 *   ohne Änderung (keine Dublette, kein Überschreiben).
 * - Verschwindet ein gebuchter Termin aus der Quelle, entsteht ein Hinweis in
 *   der Zuordnungs-Inbox; gelöscht wird nichts.
 * - Quelle ist die iCal-Adresse oder eine Kalenderverbindung (MVP-1531): deren
 *   Feed liefert das iCal-Dokument des Rückblicks, der Rest des Laufs ist gleich.
 */
class IcalSubscriptionSync {
    public const string INBOX_PLUGIN = IntegrationInboxItem::PLUGIN_ICAL_SUBSCRIPTION;

    public const int LOOKBACK_DAYS = 30;

    /** Obergrenze des Kalenderinhalts — ein Abo-Feed ist kein Archiv. */
    private const int MAX_BYTES = 10 * 1024 * 1024;

    private const int LOCK_SECONDS = 600;

    public function __construct(
        private readonly PluginHttpFactory $http,
        private readonly ImportSourceFactory $sources,
        private readonly EntitySpecRegistry $specs,
        private readonly CalendarImportFeeds $feeds,
    ) {}

    /** @return array{created: int, updated: int, unchanged: int, adopted: int, staged: int, failed: int, removed: int} */
    public function sync(IcalSubscription $subscription): array {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'adopted' => 0, 'staged' => 0, 'failed' => 0, 'removed' => 0];

        $organization = Organization::query()->withoutGlobalScopes()->find($subscription->organization_id);
        if (! $organization instanceof Organization) {
            return $counts;
        }

        $lock = Cache::lock('ical-subscription-sync:' . $subscription->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return $counts;
        }

        try {
            return OrganizationContext::run($organization, fn (): array => $this->run($subscription, $organization, $counts));
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{created: int, updated: int, unchanged: int, adopted: int, staged: int, failed: int, removed: int}  $counts
     * @return array{created: int, updated: int, unchanged: int, adopted: int, staged: int, failed: int, removed: int}
     */
    private function run(IcalSubscription $subscription, Organization $organization, array $counts): array {
        $tz = Tz::ofOrganization($organization);
        $now = CarbonImmutable::now($tz);
        $from = $now->startOfDay()->subDays(self::LOOKBACK_DAYS);

        try {
            $content = $subscription->calendar_source !== null
                ? $this->fetchConnection($subscription, $organization, $from, $now)
                : $this->fetch($subscription);
        } catch (Throwable $e) {
            $this->finish($subscription, IcalSyncStatus::Failed, $e->getMessage(), null);

            return $counts;
        }
        if ($content === null) {
            $this->finish($subscription, IcalSyncStatus::NotModified, null, $counts);

            return $counts;
        }

        $spec = $this->specs->for($subscription->import_entity);

        try {
            $source = $this->sources->icalFromContent($content, $subscription->import_entity, $organization, [
                'category_allowlist' => (array) ($subscription->category_allowlist ?? []),
                'recurrence_from' => $from->toDateString(),
                'recurrence_until' => $now->toDateString(),
            ]);

            $seen = [];
            foreach ($source->eventRows() as [$event, $row]) {
                $start = $this->localStart($event, $tz);
                if ($start === null || $start->lt($from) || ! $this->isOver($event, $start, $now, $tz)) {
                    continue;
                }

                $hash = CryptoHelper::hash($event->uid, HashAlgorithm::SHA256);
                $seen[$hash] = true;
                $counts[$this->apply($subscription, $organization, $spec, $event, $row, $hash, $start)]++;
            }
        } catch (Throwable $e) {
            $this->finish($subscription, IcalSyncStatus::Failed, $e->getMessage(), $counts);

            return $counts;
        }

        // Leere Lieferung: kein Beweis für Löschungen (Muster ReconcilesRemoteDeletions).
        if ($seen !== []) {
            $counts['removed'] = $this->flagRemoved($subscription, $organization, array_keys($seen), $from, $now);
        }

        $this->finish($subscription, IcalSyncStatus::Ok, null, $counts);

        return $counts;
    }

    /** Inhalt der Quelle; null = unverändert seit dem letzten Abruf (HTTP 304). */
    private function fetch(IcalSubscription $subscription): ?string {
        $headers = ['Accept' => 'text/calendar, */*;q=0.5'];
        if ($subscription->http_etag !== null) {
            $headers['If-None-Match'] = $subscription->http_etag;
        }
        if ($subscription->http_last_modified !== null) {
            $headers['If-Modified-Since'] = $subscription->http_last_modified;
        }

        $url = (string) $subscription->url;
        $response = $this->http->coreClient(self::INBOX_PLUGIN, $url, 0.0, $subscription->allow_private_network)
            ->requestResponse('GET', $url, ['headers' => $headers, 'timeout' => 30]);

        if ($response->status() === 304) {
            return null;
        }
        if (! $response->successful()) {
            throw new \RuntimeException((string) __('import.ical_subscription.error.http', ['status' => $response->status()]));
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException((string) __('import.ical_subscription.error.too_large', ['mb' => self::MAX_BYTES / 1024 / 1024]));
        }
        if (stripos($body, 'BEGIN:VCALENDAR') === false) {
            throw new \RuntimeException((string) __('import.ical_subscription.error.not_ical'));
        }

        $subscription->forceFill([
            'http_etag' => $response->header('ETag') !== '' ? mb_substr($response->header('ETag'), 0, 191) : null,
            'http_last_modified' => $response->header('Last-Modified') !== '' ? mb_substr($response->header('Last-Modified'), 0, 64) : null,
        ]);

        return $body;
    }

    /**
     * Termine des Rückblicks aus der Kalenderverbindung, über denselben Feed
     * wie der Einmalimport. Die Meldung bleibt allgemein wie dort; die
     * Ursache steht im Log.
     */
    private function fetchConnection(IcalSubscription $subscription, Organization $organization, CarbonImmutable $from, CarbonImmutable $until): string {
        try {
            return $this->feeds->fetch($organization, (string) $subscription->calendar_source, $from->utc()->toDateTimeImmutable(), $until->utc()->toDateTimeImmutable());
        } catch (Throwable $e) {
            Log::warning('iCal-Abo: Kalenderverbindung nicht lesbar.', ['ical_subscription_id' => $subscription->id, 'exception' => $e]);

            throw new \RuntimeException((string) __('import.error.calendar.fetch'), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'created'|'updated'|'unchanged'|'adopted'|'staged'|'failed'
     */
    private function apply(IcalSubscription $subscription, Organization $organization, \App\Services\Import\EntitySpec $spec, IcalEvent $event, array $row, string $hash, CarbonImmutable $start): string {
        $state = IcalSubscriptionItem::query()->withoutGlobalScopes()
            ->where('ical_subscription_id', $subscription->id)
            ->where('uid_hash', $hash)
            ->first();

        if ($state instanceof IcalSubscriptionItem && $state->removed_at === null
            && $state->sequence === $event->sequence
            && $event->lastModified !== null && $state->source_modified_at?->getTimestamp() === $event->lastModified->getTimestamp()) {
            $state->forceFill(['seen_at' => now()])->save();

            return 'unchanged';
        }

        if ($subscription->user !== null) {
            // Kalender einer Person: alle Termine gehören ihr, gleich wer organisiert.
            $row['user_email'] = (string) $subscription->user->email;
        }
        $fingerprint = CryptoHelper::hash(JsonHelper::encode($row), HashAlgorithm::SHA256);

        if ($state instanceof IcalSubscriptionItem && $state->fingerprint === $fingerprint && $state->removed_at === null) {
            $this->remember($subscription, $state, $event, $hash, $fingerprint, $start);

            return 'unchanged';
        }

        if ($state === null && $this->importedReference($subscription, $organization, $event->uid) !== null) {
            $this->remember($subscription, null, $event, $hash, $fingerprint, $start);

            return 'adopted';
        }

        $normalized = $spec->normalize($row);
        if ($spec->validateRow($normalized, $organization) !== []) {
            return 'failed';
        }

        [$outcome] = $spec instanceof InboxFirstSpec
            ? $spec->upsertOrStage($normalized, $organization)
            : $spec->upsert($normalized, $organization);

        if ($outcome === ImportOutcome::Failed) {
            return 'failed';
        }
        // In die Inbox gestellt oder übersprungen: ohne gemerkten Stand versucht
        // der nächste Lauf es erneut, etwa sobald das Projekt angelegt ist.
        if ($outcome === ImportOutcome::Skipped) {
            return 'staged';
        }

        $this->remember($subscription, $state, $event, $hash, $fingerprint, $start);

        return $outcome === ImportOutcome::Created ? 'created' : 'updated';
    }

    private function remember(IcalSubscription $subscription, ?IcalSubscriptionItem $state, IcalEvent $event, string $hash, string $fingerprint, CarbonImmutable $start): void {
        ($state ?? new IcalSubscriptionItem)->forceFill([
            'organization_id' => $subscription->organization_id,
            'ical_subscription_id' => $subscription->id,
            'uid' => $event->uid,
            'uid_hash' => $hash,
            'sequence' => $event->sequence,
            'source_modified_at' => $event->lastModified !== null ? CarbonImmutable::instance($event->lastModified)->utc() : null,
            'fingerprint' => $fingerprint,
            'starts_at' => $start->utc(),
            'seen_at' => now(),
            'removed_at' => null,
        ])->save();
    }

    /**
     * Gelesene Termine im Rückblick, die die Quelle nicht mehr liefert: als
     * entfernt markieren und — falls gebucht — als Hinweis in die Inbox.
     *
     * @param  list<string>  $seenHashes
     */
    private function flagRemoved(IcalSubscription $subscription, Organization $organization, array $seenHashes, CarbonImmutable $from, CarbonImmutable $now): int {
        $missing = IcalSubscriptionItem::query()->withoutGlobalScopes()
            ->where('ical_subscription_id', $subscription->id)
            ->whereNull('removed_at')
            ->whereBetween('starts_at', [$from->utc(), $now->utc()])
            ->whereNotIn('uid_hash', $seenHashes)
            ->get();

        foreach ($missing as $state) {
            $state->forceFill(['removed_at' => now()])->save();

            $record = $this->importedReference($subscription, $organization, $state->uid)?->referenceable;
            if ($record === null) {
                continue; // nie gebucht (z. B. nur in der Inbox) — nichts zu melden
            }

            IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'plugin_id' => self::INBOX_PLUGIN,
                    'dedupe_key' => self::INBOX_PLUGIN . '-removed:' . $subscription->id . ':' . $state->uid_hash,
                ],
                [
                    'source' => 'ical',
                    'target_type' => $record->getMorphClass(),
                    'external_type' => 'ical-event',
                    'external_id' => mb_substr($state->uid, 0, 191),
                    'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                    'referenceable_type' => $record->getMorphClass(),
                    'referenceable_id' => $record->getKey(),
                    'status' => IntegrationInboxStatus::Open,
                    'display_title' => (string) __('import.ical_subscription.removed_title', ['name' => $subscription->name]),
                    'display_subtitle' => $state->starts_at !== null ? CarbonFmt::fdatetime($state->starts_at, Tz::ofOrganization($organization)) : null,
                    'remote_snapshot' => [
                        'reason' => IntegrationInboxItem::REASON_REMOTE_DELETED,
                        // Gebuchte Zeit bleibt stehen; löschen oder korrigieren entscheidet ein Mensch.
                        'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                        'subscription_id' => $subscription->id,
                    ],
                ],
            );
        }

        return $missing->count();
    }

    /** Bindung eines Zeitimports derselben Entität (Einmalimport oder Abo) an diese UID. */
    private function importedReference(IcalSubscription $subscription, Organization $organization, string $uid): ?ExternalReference {
        $model = $subscription->import_entity->modelClass();
        if ($model === null) {
            return null;
        }

        return ExternalReference::query()
            ->forPlugin($organization, ProjectTimeSpec::TIME_IMPORT_PLUGIN)
            ->where('referenceable_type', (new $model)->getMorphClass())
            ->forExternalId(ProjectTimeSpec::EXTERNAL_KEY_PREFIX . $uid)
            ->first();
    }

    private function localStart(IcalEvent $event, string $tz): ?CarbonImmutable {
        if ($event->date === null || $event->startTime === null) {
            return null;
        }

        return CarbonImmutable::parse($event->date . ' ' . $event->startTime, $tz);
    }

    /** Nur Vergangenes ist Arbeitszeit: der Termin muss zu Ende sein. */
    private function isOver(IcalEvent $event, CarbonImmutable $start, CarbonImmutable $now, string $tz): bool {
        if ($event->endTime === null) {
            return $start->lt($now);
        }
        $end = CarbonImmutable::parse($start->toDateString() . ' ' . $event->endTime, $tz);
        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return $end->lte($now);
    }

    /** @param array<string, int>|null $counts */
    private function finish(IcalSubscription $subscription, IcalSyncStatus $status, ?string $error, ?array $counts): void {
        if ($error !== null) {
            Log::warning('iCal-Abo: Abruf fehlgeschlagen.', ['ical_subscription_id' => $subscription->id, 'error' => $error]);
        }

        $subscription->forceFill([
            'last_synced_at' => now(),
            'last_status' => $status,
            'last_error' => $error !== null ? mb_substr($error, 0, 500) : null,
            'last_result' => $counts ?? $subscription->last_result,
        ])->save();
    }
}
