<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\{Customer, ForeignCustomer, Project, SearchDocument, User};
use App\Services\Search\Engine\{SearchEngineResolver, SearchMatchCompiler};
use App\Services\Search\Query\{ParsedSearchQuery, SearchQueryParser};
use App\Support\{OrganizationContext, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tätigkeitsrecherche (Feature 153, MVP-771/773): durchsucht den Index
 * rechte- und mandantensicher und liefert Treffer, die Übersicht je
 * Kunde/Endkunde und die Zähler je Quelle. Seite, Palette und KI-Antwort
 * nutzen dieselben Abfragen.
 */
final class ActivitySearchService {
    public function __construct(
        private readonly SearchQueryParser $parser,
        private readonly SearchEngineResolver $engines,
        private readonly ActivitySearchVisibility $visibility,
        private readonly SearchResultLinker $linker,
    ) {}

    public function search(User $user, ActivitySearchCriteria $criteria, ?int $perPage = null, int $page = 1): ActivitySearchResult {
        $perPage ??= (int) config('search.per_page', 25);
        $organizationId = self::organizationId($user);
        $parsed = $this->parse($criteria, $organizationId);

        if (! $this->searchable($criteria, $parsed)) {
            return new ActivitySearchResult(new LengthAwarePaginator([], 0, $perPage, $page), [], [], $parsed, false, false);
        }

        $compiler = $this->engines->compiler();
        $query = $this->baseQuery($user, $organizationId, $criteria, $parsed, $compiler, true);
        $relevance = $criteria->sort === ActivitySearchCriteria::SORT_RELEVANCE && $compiler->orderByRelevance($query, $parsed);
        $query->orderByDesc('search_documents.occurred_at')->orderByDesc('search_documents.id');

        // Dokumente seitenweise laden, als Treffer ausliefern (eigener Paginator
        // statt setCollection: die Elementart wechselt).
        $documents = $query->paginate($perPage, ['*'], 'page', $page);
        $hits = new LengthAwarePaginator(
            $this->hydrate($documents->getCollection(), $parsed),
            $documents->total(),
            $documents->perPage(),
            $documents->currentPage(),
            ['path' => $documents->path(), 'pageName' => 'page'],
        );

        return new ActivitySearchResult(
            $hits,
            $this->aggregates($user, $organizationId, $criteria, $parsed, $compiler),
            $this->typeCounts($user, $organizationId, $criteria, $parsed, $compiler),
            $parsed,
            true,
            $relevance,
        );
    }

    /**
     * Die besten Treffer ohne Seitenweise (Palette, KI-Antwort).
     *
     * @return list<ActivitySearchHit>
     */
    public function topHits(User $user, ActivitySearchCriteria $criteria, int $limit): array {
        $organizationId = self::organizationId($user);
        $parsed = $this->parse($criteria, $organizationId);
        if (! $this->searchable($criteria, $parsed)) {
            return [];
        }

        $compiler = $this->engines->compiler();
        $query = $this->baseQuery($user, $organizationId, $criteria, $parsed, $compiler, true);
        if ($criteria->sort === ActivitySearchCriteria::SORT_RELEVANCE) {
            $compiler->orderByRelevance($query, $parsed);
        }
        $query->orderByDesc('search_documents.occurred_at')->orderByDesc('search_documents.id')->limit($limit);

        return $this->hydrate($query->get(), $parsed);
    }

    /**
     * Gruppe „Tätigkeiten" der Command-Palette.
     *
     * @param  array{from?: string|null, to?: string|null, person?: int|null, customer?: int|null}  $filters
     * @return list<array{id: string, title: string, subtitle: string|null, url: string}>
     */
    public function typeAhead(User $user, string $term, array $filters = [], int $limit = 5): array {
        $criteria = new ActivitySearchCriteria(
            query: $term,
            from: $filters['from'] ?? null,
            to: $filters['to'] ?? null,
            personId: $filters['person'] ?? null,
            customerId: $filters['customer'] ?? null,
        );

        $items = [];
        foreach ($this->topHits($user, $criteria, $limit) as $hit) {
            $subtitle = implode(' · ', array_filter(
                [$hit->dateLabel(), $hit->type->label(), $hit->customerLabel(), $hit->projectName],
                static fn(?string $v): bool => $v !== null && $v !== '',
            ));
            $items[] = [
                'id' => $hit->type->value . ':' . $hit->sourceId,
                'title' => $hit->title,
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'url' => $hit->url ?? route('search.index', $criteria->toParameters()),
            ];
        }

        return $items;
    }

    private function parse(ActivitySearchCriteria $criteria, ?int $organizationId): ParsedSearchQuery {
        return $criteria->query !== '' && $organizationId !== null
            ? $this->parser->parse($criteria->query, $organizationId, $criteria->similar)
            : new ParsedSearchQuery;
    }

    /** Ohne Suchwort bzw. Bezugsfilter gibt es nichts zu zeigen („alles" ist keine Antwort). */
    private function searchable(ActivitySearchCriteria $criteria, ParsedSearchQuery $parsed): bool {
        return $parsed->hasPositive() || $criteria->hasEntityFilter();
    }

    /** @return Builder<SearchDocument> */
    private function baseQuery(User $user, ?int $organizationId, ActivitySearchCriteria $criteria, ParsedSearchQuery $parsed, SearchMatchCompiler $compiler, bool $withTypes): Builder {
        // Ohne Organisation greift `IS NULL` — kein Treffer (fail-closed).
        $query = SearchDocument::query()->withoutGlobalScopes()->where('search_documents.organization_id', $organizationId);
        $this->visibility->apply($query, $user);

        if ($withTypes && $criteria->types !== []) {
            $query->whereIn('search_documents.source_type', array_map(static fn($t): string => $t->value, $criteria->types));
        }
        if ($criteria->customerId !== null) {
            $customerId = $criteria->customerId;
            $query->where(static fn(Builder $q) => $q
                ->where('search_documents.customer_id', $customerId)
                ->orWhereIn('search_documents.foreign_customer_id', static fn($foreign) => $foreign
                    ->select('id')
                    ->from('foreign_customers')
                    ->where('organization_id', $organizationId)
                    ->where('customer_id', $customerId)));
        }
        if ($criteria->foreignCustomerId !== null) {
            $query->where('search_documents.foreign_customer_id', $criteria->foreignCustomerId);
        }
        if ($criteria->projectId !== null) {
            $projectId = $criteria->projectId;
            $query->where(static fn(Builder $q) => $q
                ->where('search_documents.project_id', $projectId)
                ->orWhereIn('search_documents.project_id', static fn($children) => $children
                    ->select('id')
                    ->from('projects')
                    ->where('organization_id', $organizationId)
                    ->where('parent_id', $projectId)));
        }
        if ($criteria->personId !== null) {
            $personId = $criteria->personId;
            $query->where(static fn(Builder $q) => $q
                ->where('search_documents.user_id', $personId)
                ->orWhere('search_documents.assigned_user_id', $personId));
        }

        // Kalendertage in der Anzeige-Zeitzone, Obergrenze halboffen.
        $timezone = Tz::current();
        if ($criteria->from !== null) {
            $query->where('search_documents.occurred_at', '>=', CarbonImmutable::parse($criteria->from, $timezone)->startOfDay()->utc());
        }
        if ($criteria->to !== null) {
            $query->where('search_documents.occurred_at', '<', CarbonImmutable::parse($criteria->to, $timezone)->startOfDay()->addDay()->utc());
        }

        if (! $parsed->isEmpty()) {
            $compiler->apply($query, $parsed);
        }

        return $query;
    }

    /** @return list<ActivitySearchAggregate> */
    private function aggregates(User $user, ?int $organizationId, ActivitySearchCriteria $criteria, ParsedSearchQuery $parsed, SearchMatchCompiler $compiler): array {
        $rows = $this->baseQuery($user, $organizationId, $criteria, $parsed, $compiler, true)
            ->toBase()
            ->selectRaw('search_documents.customer_id, search_documents.foreign_customer_id, COUNT(*) AS hits, MIN(search_documents.occurred_at) AS first_at, MAX(search_documents.occurred_at) AS last_at')
            ->groupBy('search_documents.customer_id', 'search_documents.foreign_customer_id')
            ->orderByDesc('hits')
            ->limit((int) config('search.aggregate_limit', 12))
            ->get();

        $customers = $this->names(Customer::class, $organizationId, $rows->pluck('customer_id'));
        $foreign = $this->names(ForeignCustomer::class, $organizationId, $rows->pluck('foreign_customer_id'));

        $aggregates = [];
        foreach ($rows as $row) {
            $customerId = $row->customer_id !== null ? (int) $row->customer_id : null;
            $foreignId = $row->foreign_customer_id !== null ? (int) $row->foreign_customer_id : null;
            $aggregates[] = new ActivitySearchAggregate(
                $customerId,
                $customerId !== null ? ($customers[$customerId] ?? null) : null,
                $foreignId,
                $foreignId !== null ? ($foreign[$foreignId] ?? null) : null,
                (int) $row->hits,
                $row->first_at !== null ? CarbonImmutable::parse((string) $row->first_at, 'UTC') : null,
                $row->last_at !== null ? CarbonImmutable::parse((string) $row->last_at, 'UTC') : null,
            );
        }

        return $aggregates;
    }

    /** @return array<string, int> */
    private function typeCounts(User $user, ?int $organizationId, ActivitySearchCriteria $criteria, ParsedSearchQuery $parsed, SearchMatchCompiler $compiler): array {
        return $this->baseQuery($user, $organizationId, $criteria, $parsed, $compiler, false)
            ->toBase()
            ->selectRaw('search_documents.source_type, COUNT(*) AS hits')
            ->groupBy('search_documents.source_type')
            ->pluck('hits', 'source_type')
            ->map(static fn($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  Collection<int, SearchDocument>  $documents
     * @return list<ActivitySearchHit>
     */
    private function hydrate(Collection $documents, ParsedSearchQuery $parsed): array {
        if ($documents->isEmpty()) {
            return [];
        }

        $organizationId = (int) $documents->first()->organization_id;
        $userIds = $documents->pluck('user_id')->filter()->unique()->values()->all();
        $users = $userIds === [] ? collect() : User::query()->withoutGlobalScopes()->whereKey($userIds)->pluck('name', 'id');
        $projects = $this->names(Project::class, $organizationId, $documents->pluck('project_id'));
        $customers = $this->names(Customer::class, $organizationId, $documents->pluck('customer_id'));
        $foreign = $this->names(ForeignCustomer::class, $organizationId, $documents->pluck('foreign_customer_id'));
        $urls = $this->linker->urls($documents);
        $words = $parsed->highlightWords();

        $hits = [];
        foreach ($documents as $document) {
            $title = $document->title !== '' ? $document->title : $document->source_type->label();
            $hits[] = new ActivitySearchHit(
                type: $document->source_type,
                sourceId: $document->source_id,
                title: $title,
                titleSegments: SearchSnippet::segments($title, $words, 255),
                snippet: SearchSnippet::segments($document->excerpt, $words),
                occurredAt: $document->occurred_at,
                dateOnly: $document->date_only,
                minutes: $document->minutes,
                userName: $document->user_id !== null ? ($users[$document->user_id] ?? null) : null,
                customerId: $document->customer_id,
                customerName: $document->customer_id !== null ? ($customers[$document->customer_id] ?? null) : null,
                foreignCustomerId: $document->foreign_customer_id,
                foreignCustomerName: $document->foreign_customer_id !== null ? ($foreign[$document->foreign_customer_id] ?? null) : null,
                projectId: $document->project_id,
                projectName: $document->project_id !== null ? ($projects[$document->project_id] ?? null) : null,
                url: $urls[$document->source_type->value . ':' . $document->source_id] ?? null,
                excerpt: $document->excerpt,
            );
        }

        return $hits;
    }

    /**
     * @param  class-string<Model>  $class
     * @param  Collection<int, mixed>  $ids
     * @return array<int, string>
     */
    private function names(string $class, ?int $organizationId, Collection $ids): array {
        $ids = $ids->filter()->map(static fn($id): int => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return $class::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereKey($ids)
            ->pluck('name', 'id')
            ->map(static fn($name): string => (string) $name)
            ->all();
    }

    private static function organizationId(User $user): ?int {
        $id = OrganizationContext::currentId() ?? $user->organization_id;

        return $id !== null ? (int) $id : null;
    }
}
