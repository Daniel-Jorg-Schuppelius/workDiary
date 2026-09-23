<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchFacets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\Classification\Tag;
use App\Models\Concerns\HasTags;
use App\Models\Knowledge\ContentCollection;
use App\Models\Platform\User;
use App\Models\Search\SearchDocument;
use App\Services\Collections\{CollectableTypes, ContentCollectionService};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Schlagwort und Sammlung als Facetten der Recherche (MVP-812, Feature 155).
 *
 * Beide Filter laufen als Unterabfrage gegen `taggables` bzw.
 * `collection_items` statt als Spalte in `search_documents`: Einsortieren in
 * eine Sammlung speichert die Quelle nicht, ein denormalisierter Wert wäre bis
 * zum nächsten Neuaufbau falsch. Die Sichtbarkeit der Treffer regelt weiter
 * {@see ActivitySearchVisibility}; eine Sammlung zählt nur, wenn die Person sie
 * sehen darf — samt ihrer sichtbaren Untersammlungen.
 */
final class ActivitySearchFacets {
    public function __construct(
        private readonly CollectableTypes $collectables,
        private readonly ContentCollectionService $collections,
    ) {}

    /** @param  Builder<SearchDocument>  $query */
    public function applyTag(Builder $query, int $tagId): void {
        $types = self::taggableTypes();
        $query->where(static function (Builder $outer) use ($types, $tagId): void {
            foreach ($types as $type) {
                $morph = (new ($type->modelClass())())->getMorphClass();
                $outer->orWhere(static fn (Builder $q) => $q
                    ->where('search_documents.source_type', $type->value)
                    ->whereIn('search_documents.source_id', static fn ($tagged) => $tagged
                        ->select('taggable_id')
                        ->from('taggables')
                        ->where('taggable_type', $morph)
                        ->where('tag_id', $tagId)));
            }
        });
    }

    /** @param  Builder<SearchDocument>  $query */
    public function applyCollection(Builder $query, User $user, ?int $organizationId, int $collectionId): void {
        $collectionIds = Gate::forUser($user)->allows('viewAny', ContentCollection::class)
            ? $this->collections->visibleSubtreeIds($user, $organizationId, $collectionId)
            : [];
        $types = $this->collectableTypes();
        if ($collectionIds === [] || $types === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(static function (Builder $outer) use ($types, $collectionIds): void {
            foreach ($types as $type) {
                $morph = (new ($type->modelClass())())->getMorphClass();
                $outer->orWhere(static fn (Builder $q) => $q
                    ->where('search_documents.source_type', $type->value)
                    ->whereIn('search_documents.source_id', static fn ($items) => $items
                        ->select('collectable_id')
                        ->from('collection_items')
                        ->where('collectable_type', $morph)
                        ->whereIn('collection_id', $collectionIds)));
            }
        });
    }

    /**
     * Häufigste Schlagwörter der Treffer. `$base` liefert je Aufruf eine frische
     * Trefferabfrage (mit Sichtbarkeit und allen Filtern).
     *
     * @param  callable(): Builder<SearchDocument>  $base
     * @return list<array{id: int, name: string, hits: int}>
     */
    public function tagCounts(callable $base, ?int $organizationId, int $limit): array {
        $counts = [];
        foreach (self::taggableTypes() as $type) {
            $morph = (new ($type->modelClass())())->getMorphClass();
            $rows = $base()
                ->toBase()
                ->where('search_documents.source_type', $type->value)
                ->join('taggables', static fn ($join) => $join
                    ->on('taggables.taggable_id', '=', 'search_documents.source_id')
                    ->where('taggables.taggable_type', '=', $morph))
                ->selectRaw('taggables.tag_id AS tag_id, COUNT(*) AS hits')
                ->groupBy('taggables.tag_id')
                ->get();
            foreach ($rows as $row) {
                $counts[(int) $row->tag_id] = ($counts[(int) $row->tag_id] ?? 0) + (int) $row->hits;
            }
        }
        if ($counts === []) {
            return [];
        }

        arsort($counts);
        $counts = array_slice($counts, 0, $limit, true);
        $names = Tag::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereKey(array_keys($counts))
            ->pluck('name', 'id');

        $facets = [];
        foreach ($counts as $tagId => $hits) {
            if (isset($names[$tagId])) {
                $facets[] = ['id' => $tagId, 'name' => (string) $names[$tagId], 'hits' => $hits];
            }
        }

        return $facets;
    }

    /**
     * Quellen mit Schlagwörtern.
     *
     * @return list<SearchSourceType>
     */
    public static function taggableTypes(): array {
        return array_values(array_filter(
            SearchSourceType::cases(),
            static fn (SearchSourceType $type): bool => in_array(HasTags::class, class_uses_recursive($type->modelClass()), true),
        ));
    }

    /**
     * Quellen, die in Sammlungen liegen können.
     *
     * @return list<SearchSourceType>
     */
    private function collectableTypes(): array {
        return array_values(array_filter(
            SearchSourceType::cases(),
            fn (SearchSourceType $type): bool => $this->collectables->keyFor(new ($type->modelClass())()) !== null,
        ));
    }
}
