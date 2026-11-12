<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\WorkQueue;

use App\Models\Platform\{Organization, User};
use App\Services\Licensing\ModuleExtensions;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Zentraler Arbeitsvorrat (Feature 179): bündelt die offenen Vorgänge aller
 * angemeldeten Quellen für einen Nutzer. Liest nur; derselbe Gegenstand aus
 * zwei Quellen erscheint einmal (der dringlichere bleibt).
 *
 * Das Ergebnis liegt 60 s im Cache je Nutzer, Organisation, Sprache und
 * Quellenmenge (nur Arrays, {@see WorkItem::toArray()}); Dashboard-Kachel und
 * Seite teilen es. Die Quellen müssen keinen Stempel bedienen — ein neuer
 * Vorgang erscheint spätestens nach einer Minute, bearbeitet wird ohnehin auf
 * der Fläche der Quelle. Je Quelle zählen höchstens {@see SOURCE_LIMIT}
 * Vorgänge (die dringendsten); der Rest wird nur gezählt
 * (Leistungsanalyse 2026-11-02, perfc-06).
 */
final class WorkQueue {
    /** Höchstzahl je Quelle; darüber zählt der Vorrat nur noch. */
    public const int SOURCE_LIMIT = 200;

    private const int CACHE_TTL = 60;

    private const int CACHE_VERSION = 1;

    public function __construct(private readonly ModuleExtensions $extensions) {}

    /** @return array<string, WorkItemSource> Schlüssel → Quelle, nur die für den Nutzer verfügbaren */
    public function sourcesFor(User $viewer): array {
        $sources = [];
        foreach ($this->extensions->activeFor(WorkItemSource::class, OrganizationContext::currentId() ?? $viewer->organization_id) as $class) {
            /** @var WorkItemSource $source */
            $source = app($class);
            if ($source->available($viewer)) {
                $sources[$source->key()] = $source;
            }
        }

        return $sources;
    }

    /**
     * Sortiert: überfällig, bald fällig, offen; innerhalb nach Fälligkeit (ohne zuletzt), dann Titel.
     *
     * @param  array<string, WorkItemSource>|null  $sources  vorab ermittelte Quellen (sonst {@see sourcesFor()})
     * @return list<WorkItem>
     */
    public function itemsFor(User $viewer, Organization $organization, ?array $sources = null, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();
        $sources ??= $this->sourcesFor($viewer);
        $items = [];
        foreach ($this->cached($viewer, $organization, $sources, $now)['items'] as $row) {
            $items[] = WorkItem::fromArray($row);
        }
        // Die Dringlichkeit hängt am Lesezeitpunkt, nicht am Cache-Stand.
        usort($items, static fn (WorkItem $a, WorkItem $b): int => self::compare($a, $b, $now));

        return $items;
    }

    /**
     * Vorgänge je Quelle, die über die Obergrenze hinaus nur gezählt wurden.
     *
     * @param  array<string, WorkItemSource>|null  $sources
     * @return array<string, int> Quellenschlüssel → Anzahl
     */
    public function overflowFor(User $viewer, Organization $organization, ?array $sources = null): array {
        return $this->cached($viewer, $organization, $sources ?? $this->sourcesFor($viewer), CarbonImmutable::now())['overflow'];
    }

    /**
     * Cache-Eintrag des Nutzers verwerfen — für Tests und Stellen, die den Vorrat sofort frisch brauchen.
     *
     * @param  array<string, WorkItemSource>|null  $sources
     */
    public function forget(User $viewer, Organization $organization, ?array $sources = null): void {
        Cache::forget($this->cacheKey($viewer, $organization, $sources ?? $this->sourcesFor($viewer)));
    }

    /**
     * @param  array<string, WorkItemSource>  $sources
     * @return array{items: list<array<string, mixed>>, overflow: array<string, int>}
     */
    private function cached(User $viewer, Organization $organization, array $sources, CarbonImmutable $now): array {
        $key = $this->cacheKey($viewer, $organization, $sources);
        $cached = Cache::get($key);
        if (! is_array($cached) || ! is_array($cached['items'] ?? null) || ! is_array($cached['overflow'] ?? null)) {
            $cached = $this->collect($viewer, $organization, $sources, $now);
            Cache::put($key, $cached, self::CACHE_TTL);
        }
        $items = [];
        foreach ($cached['items'] as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }
        $overflow = [];
        foreach ($cached['overflow'] as $source => $count) {
            if (is_string($source) && is_int($count)) {
                $overflow[$source] = $count;
            }
        }

        return ['items' => $items, 'overflow' => $overflow];
    }

    /**
     * @param  array<string, WorkItemSource>  $sources
     * @return array{items: list<array<string, mixed>>, overflow: array<string, int>}
     */
    private function collect(User $viewer, Organization $organization, array $sources, CarbonImmutable $now): array {
        /** @var array<string, WorkItem> $items */
        $items = [];
        $overflow = [];
        foreach ($sources as $source) {
            try {
                $own = [];
                foreach ($source->items($viewer, $organization) as $item) {
                    $own[] = $item;
                }
            } catch (Throwable $e) {
                // Eine Quelle darf den Vorrat nicht kippen; ihr Fehler wird gemeldet.
                report($e);

                continue;
            }
            if (count($own) > self::SOURCE_LIMIT) {
                usort($own, static fn (WorkItem $a, WorkItem $b): int => self::compare($a, $b, $now));
                $overflow[$source->key()] = count($own) - self::SOURCE_LIMIT;
                $own = array_slice($own, 0, self::SOURCE_LIMIT);
            }
            foreach ($own as $item) {
                $existing = $items[$item->key] ?? null;
                if ($existing === null || self::compare($item, $existing, $now) < 0) {
                    $items[$item->key] = $item;
                }
            }
        }

        return ['items' => array_map(static fn (WorkItem $item): array => $item->toArray(), array_values($items)), 'overflow' => $overflow];
    }

    /** @param  array<string, WorkItemSource>  $sources */
    private function cacheKey(User $viewer, Organization $organization, array $sources): string {
        $keys = array_keys($sources);
        sort($keys);

        return implode(':', ['work-queue', self::CACHE_VERSION, (int) $viewer->id, (int) $organization->id, app()->getLocale(), substr((string) CryptoHelper::hash(implode(',', $keys)), 0, 16)]);
    }

    private static function compare(WorkItem $a, WorkItem $b, CarbonImmutable $now): int {
        return [$a->urgency($now)->rank(), $a->dueAt === null, $a->dueAt?->getTimestamp(), mb_strtolower($a->title)]
            <=> [$b->urgency($now)->rank(), $b->dueAt === null, $b->dueAt?->getTimestamp(), mb_strtolower($b->title)];
    }
}
