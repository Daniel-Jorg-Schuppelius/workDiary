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

use App\Enums\User\Permission;
use App\Enums\WorkQueue\{WorkItemCategory, WorkItemDueFilter};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Zentraler Arbeitsvorrat (Feature 179, MVP-1362): alle offenen Vorgänge des
 * Nutzers aus den angemeldeten Quellen. Nur Lesen und Verlinken — bearbeitet
 * wird auf der Fläche der Quelle.
 *
 * Liegezeit (MVP-1670): Tage ohne Bewegung je Vorgang, Sortierung `idle` und
 * Filter „nur ruhende“ ab der Schwelle der Organisation. Gezählt wird am
 * Vorgang, nie je Person.
 */
class WorkQueueController extends Controller {
    use ResolvesCurrentOrganization;

    private const array SORTS = ['urgency', 'due', 'idle', 'title', 'source'];

    public function __invoke(Request $request, WorkQueue $queue): View {
        $viewer = $this->authUser();
        $now = CarbonImmutable::now();
        $sources = $queue->sourcesFor($viewer);

        $category = WorkItemCategory::tryFrom($request->string('category')->toString());
        $source = $request->string('source')->toString();
        $source = array_key_exists($source, $sources) ? $source : '';
        $due = WorkItemDueFilter::tryFrom($request->string('due')->toString());
        $mine = $request->boolean('mine');
        $dormant = $request->boolean('dormant');
        $dormantDays = $queue->dormantDays($this->currentOrganization());
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'urgency', 'asc');

        $items = array_values(array_filter(
            $queue->itemsFor($viewer, $this->currentOrganization(), $sources, $now),
            static fn (WorkItem $item): bool => ($category === null || $sources[$item->source]->category() === $category)
                && ($source === '' || $item->source === $source)
                && ($due === null || $due->matches($item->dueAt, $now))
                && (! $mine || $item->personal)
                && (! $dormant || $item->isDormant($now, $dormantDays)),
        ));
        // Die Dringlichkeit ist die Grundordnung des Vorrats; andere Spalten sortieren stabil darüber.
        if ($sort !== 'urgency') {
            usort($items, static fn (WorkItem $a, WorkItem $b): int => match ($sort) {
                'due' => [$a->dueAt === null, $a->dueAt?->getTimestamp()] <=> [$b->dueAt === null, $b->dueAt?->getTimestamp()],
                // Liegezeit: aufsteigend kürzeste zuerst, absteigend längste zuerst (Richtung schon hier, s. u.).
                'idle' => ($dir === 'desc' ? -1 : 1) * ($a->idleDays($now) <=> $b->idleDays($now)),
                'title' => mb_strtolower($a->title) <=> mb_strtolower($b->title),
                default => $sources[$a->source]->label() <=> $sources[$b->source]->label(),
            });
        }
        if ($sort === 'idle') {
            // Vorgänge ohne „zuletzt bewegt“ stehen in beiden Richtungen am Ende.
            $items = [...array_filter($items, static fn (WorkItem $item): bool => $item->movedAt !== null), ...array_filter($items, static fn (WorkItem $item): bool => $item->movedAt === null)];
        } elseif ($dir === 'desc') {
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
            // Über die Obergrenze je Quelle hinaus nur gezählt (perfc-06).
            'overflow' => (int) array_sum($queue->overflowFor($viewer, $this->currentOrganization(), $sources)),
            'filters' => ['category' => $category->value ?? '', 'source' => $source, 'due' => $due->value ?? '', 'mine' => $mine, 'dormant' => $dormant],
            'dormantDays' => $dormantDays,
            'canConfigure' => Gate::allows(Permission::OrganizationUpdate->value),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
