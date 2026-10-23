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
use App\Modules\ModuleRegistry;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Zentraler Arbeitsvorrat (Feature 179): bündelt die offenen Vorgänge aller
 * angemeldeten Quellen für einen Nutzer. Liest nur; derselbe Gegenstand aus
 * zwei Quellen erscheint einmal (der dringlichere bleibt).
 */
final class WorkQueue {
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return array<string, WorkItemSource> Schlüssel → Quelle, nur die für den Nutzer verfügbaren */
    public function sourcesFor(User $viewer): array {
        $sources = [];
        foreach ($this->modules->extensions(WorkItemSource::class) as $class) {
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
        $items = [];
        foreach ($sources ?? $this->sourcesFor($viewer) as $source) {
            try {
                foreach ($source->items($viewer, $organization) as $item) {
                    $existing = $items[$item->key] ?? null;
                    if ($existing === null || self::compare($item, $existing, $now) < 0) {
                        $items[$item->key] = $item;
                    }
                }
            } catch (Throwable $e) {
                // Eine Quelle darf den Vorrat nicht kippen; ihr Fehler wird gemeldet.
                report($e);
            }
        }

        $items = array_values($items);
        usort($items, static fn (WorkItem $a, WorkItem $b): int => self::compare($a, $b, $now));

        return $items;
    }

    private static function compare(WorkItem $a, WorkItem $b, CarbonImmutable $now): int {
        return [$a->urgency($now)->rank(), $a->dueAt === null, $a->dueAt?->getTimestamp(), mb_strtolower($a->title)]
            <=> [$b->urgency($now)->rank(), $b->dueAt === null, $b->dueAt?->getTimestamp(), mb_strtolower($b->title)];
    }
}
