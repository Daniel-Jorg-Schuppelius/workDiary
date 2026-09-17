<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeHubService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\{Tag, User};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Einstieg „Wissen“ (MVP-813, Feature 155): Notizen, Ideenlandkarten,
 * Wissensartikel, Dokumente und Lerninhalte in einer Liste, gefiltert nach
 * Titel, Typ, Schlagwort und Sammlung. Jeder Typ läuft durch sein eigenes Tor
 * aus {@see CollectableTypes}; die Liste zeigt nie mehr als die jeweilige
 * Fachliste.
 */
class KnowledgeHubService {
    /** Kandidaten je Typ (zuletzt geändert zuerst) — der Einstieg ist Übersicht, keine Volltextsuche. */
    public const CANDIDATES_PER_TYPE = 150;

    public const TAG_FACET_LIMIT = 15;

    public function __construct(
        private readonly CollectableTypes $types,
        private readonly ContentCollectionService $collections,
    ) {}

    /**
     * @return list<array{type: string, model: Model, title: string, url: string, icon: string, label: string, updated_at: CarbonInterface|null, tags: list<array{id: int, name: string}>}>
     */
    public function items(User $viewer, string $query = '', ?string $type = null, ?int $tagId = null, ?int $collectionId = null): array {
        $collectionIds = $collectionId !== null
            ? $this->collections->visibleSubtreeIds($viewer, $viewer->organization_id, $collectionId)
            : null;
        if ($collectionIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->types->availableKeys($viewer) as $key) {
            if ($type !== null && $type !== $key) {
                continue;
            }
            $withTags = $this->types->hasTags($key);
            if ($tagId !== null && ! $withTags) {
                continue;
            }
            $builder = $this->types->scopedQuery($key, $viewer);
            if ($builder === null) {
                continue;
            }

            $this->filter($builder, $key, $query, $tagId, $collectionIds);
            $models = $builder
                ->when($withTags, static fn (Builder $q) => $q->with('tags:id,name'))
                ->latest($builder->getModel()->qualifyColumn('updated_at'))
                ->limit(self::CANDIDATES_PER_TYPE)
                ->get()
                ->all();

            foreach ($this->types->authorized($key, $viewer, $models) as $model) {
                $updatedAt = $model->getAttribute('updated_at');
                $rows[] = [
                    'type' => $key,
                    'model' => $model,
                    'title' => $this->types->title($model),
                    'url' => $this->types->url($model),
                    'icon' => $this->types->icon($key),
                    'label' => $this->types->label($key),
                    'updated_at' => $updatedAt instanceof CarbonInterface ? $updatedAt : null,
                    'tags' => $withTags ? $this->tagsOf($model) : [],
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => ($b['updated_at']?->getTimestamp() ?? 0) <=> ($a['updated_at']?->getTimestamp() ?? 0));

        return $rows;
    }

    /**
     * Häufigste Schlagwörter der gelisteten Inhalte — nur aus dem, was die
     * Person sieht, damit keine Stichworte verborgener Inhalte auftauchen.
     *
     * @param  list<array{tags: list<array{id: int, name: string}>}>  $rows
     * @return list<array{id: int, name: string, hits: int}>
     */
    public function tagFacets(array $rows): array {
        $counts = [];
        $names = [];
        foreach ($rows as $row) {
            foreach ($row['tags'] as $tag) {
                $counts[$tag['id']] = ($counts[$tag['id']] ?? 0) + 1;
                $names[$tag['id']] = $tag['name'];
            }
        }
        arsort($counts);

        $facets = [];
        foreach (array_slice($counts, 0, self::TAG_FACET_LIMIT, true) as $id => $hits) {
            $facets[] = ['id' => (int) $id, 'name' => $names[$id], 'hits' => $hits];
        }

        return $facets;
    }

    /**
     * @param  Builder<\App\Models\CommunicationNote>|Builder<\App\Models\IdeaMap>|Builder<\App\Models\Document>|Builder<\App\Models\Learning\LearningCourse>|Builder<\App\Models\Learning\LearningPath>|Builder<\App\Models\KnowledgeArticle>  $builder
     * @param  list<int>|null  $collectionIds
     */
    private function filter(Builder $builder, string $key, string $query, ?int $tagId, ?array $collectionIds): void {
        $model = $builder->getModel();

        if ($query !== '') {
            $builder->whereLikeEscaped($model->qualifyColumn($this->types->titleColumn($key)), $query);
        }
        if ($tagId !== null) {
            $builder->whereHas('tags', static fn (Builder $q) => $q->where('tags.id', $tagId));
        }
        if ($collectionIds !== null) {
            $morph = $model->getMorphClass();
            $builder->whereIn($model->qualifyColumn($model->getKeyName()), static fn ($items) => $items
                ->select('collectable_id')
                ->from('collection_items')
                ->where('collectable_type', $morph)
                ->whereIn('collection_id', $collectionIds));
        }
    }

    /** @return list<array{id: int, name: string}> */
    private function tagsOf(Model $model): array {
        $tags = [];
        foreach ($model->getRelationValue('tags') ?? [] as $tag) {
            if ($tag instanceof Tag) {
                $tags[] = ['id' => (int) $tag->id, 'name' => (string) $tag->name];
            }
        }

        return $tags;
    }
}
