<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkQueueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\WorkQueue;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\View\View;

/**
 * Zentraler Arbeitsvorrat (Feature 179, MVP-1362): alle offenen Vorgänge des
 * Nutzers aus den angemeldeten Quellen. Nur Lesen und Verlinken — bearbeitet
 * wird auf der Fläche der Quelle.
 */
class WorkQueueController extends Controller {
    use ResolvesCurrentOrganization;

    private const SORTS = ['urgency', 'due', 'title', 'source'];

    public function __invoke(Request $request, WorkQueue $queue): View {
        $viewer = $this->authUser();
        $now = CarbonImmutable::now();
        $sources = $queue->sourcesFor($viewer);

        $category = WorkItemCategory::tryFrom($request->string('category')->toString());
        $source = $request->string('source')->toString();
        $source = array_key_exists($source, $sources) ? $source : '';
        $mine = $request->boolean('mine');
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'urgency', 'asc');

        $items = array_values(array_filter(
            $queue->itemsFor($viewer, $this->currentOrganization(), $sources, $now),
            static fn (WorkItem $item): bool => ($category === null || $sources[$item->source]->category() === $category)
                && ($source === '' || $item->source === $source)
                && (! $mine || $item->personal),
        ));
        // Die Dringlichkeit ist die Grundordnung des Vorrats; andere Spalten sortieren stabil darüber.
        if ($sort !== 'urgency') {
            usort($items, static fn (WorkItem $a, WorkItem $b): int => match ($sort) {
                'due' => [$a->dueAt === null, $a->dueAt?->getTimestamp()] <=> [$b->dueAt === null, $b->dueAt?->getTimestamp()],
                'title' => mb_strtolower($a->title) <=> mb_strtolower($b->title),
                default => $sources[$a->source]->label() <=> $sources[$b->source]->label(),
            });
        }
        if ($dir === 'desc') {
            $items = array_reverse($items);
        }

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('work-queue.index', [
            'items' => new LengthAwarePaginator(
                array_slice($items, ($page - 1) * $perPage, $perPage),
                count($items),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
            ),
            'sources' => $sources,
            'now' => $now,
            'filters' => ['category' => $category->value ?? '', 'source' => $source, 'mine' => $mine],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
