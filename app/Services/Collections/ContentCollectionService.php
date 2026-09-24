<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Knowledge\{ContentCollection, ContentCollectionItem};
use App\Models\Platform\{Organization, User};
use App\Services\Support\Content\ContentSubjectResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sammlungen (MVP-809, Feature 155): Baum pflegen, Inhalte aufnehmen und die
 * Inhalte einer Sammlung so ausgeben, wie die Person sie sehen darf.
 */
class ContentCollectionService {
    public function __construct(
        private readonly CollectableTypes $types,
        private readonly ContentSubjectResolver $subjects,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, parent_id?: int|null, visibility?: string|null}  $attributes
     */
    public function create(Organization $organization, User $actor, array $attributes): ContentCollection {
        $parent = $this->parent($organization, $actor, $attributes['parent_id'] ?? null);
        if ($parent !== null && $parent->depth() >= ContentCollection::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => (string) __('collections.errors.too_deep', ['max' => ContentCollection::MAX_DEPTH])]);
        }

        return ContentCollection::query()->create([
            'organization_id' => $organization->id,
            'parent_id' => $parent?->id,
            'title' => trim($attributes['title']),
            'description' => $attributes['description'] ?? null,
            'visibility' => $this->visibility($attributes['visibility'] ?? null),
            'position' => (int) ContentCollection::query()->where('parent_id', $parent?->id)->max('position') + 1,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Umbenennen, beschreiben, umhängen — ohne Zyklus und ohne den Baum über
     * die Tiefengrenze zu schieben.
     *
     * @param  array{title: string, description?: string|null, parent_id?: int|null, visibility?: string|null}  $attributes
     */
    public function update(ContentCollection $collection, User $actor, array $attributes): ContentCollection {
        $organization = Organization::query()->findOrFail($collection->organization_id);
        $parent = $this->parent($organization, $actor, $attributes['parent_id'] ?? null);

        if ($parent !== null && $collection->isAncestorOrSelf($parent)) {
            throw ValidationException::withMessages(['parent_id' => (string) __('collections.errors.cycle')]);
        }
        if ($parent !== null && $parent->depth() + 1 + $collection->subtreeHeight() > ContentCollection::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => (string) __('collections.errors.too_deep', ['max' => ContentCollection::MAX_DEPTH])]);
        }

        $collection->update([
            'parent_id' => $parent?->id,
            'title' => trim($attributes['title']),
            'description' => $attributes['description'] ?? null,
            'visibility' => $this->visibility($attributes['visibility'] ?? $collection->visibility),
        ]);

        return $collection;
    }

    /** Archivieren statt Löschen — die Zuordnungen bleiben erhalten. */
    public function archive(ContentCollection $collection): void {
        $collection->forceFill(['archived_at' => now()])->saveQuietly();
        $collection->audit('archived', []);
    }

    public function restore(ContentCollection $collection): void {
        $collection->forceFill(['archived_at' => null])->saveQuietly();
        $collection->audit('restored', []);
    }

    /**
     * Inhalt aufnehmen. Liegt er schon in der Sammlung, bleibt es bei einem
     * Eintrag — Mehrfachzugehörigkeit heißt: mehrere Sammlungen, nicht Kopien.
     */
    public function addItem(ContentCollection $collection, User $actor, Model $item): ContentCollectionItem {
        if ($this->types->keyFor($item) === null) {
            throw ValidationException::withMessages(['item' => (string) __('collections.errors.type_not_allowed')]);
        }
        // Wer einen Inhalt nicht sehen darf, kann ihn auch nicht einsortieren.
        if ((int) $item->getAttribute('organization_id') !== (int) $collection->organization_id || ! $this->types->isVisibleTo($item, $actor)) {
            throw ValidationException::withMessages(['item' => (string) __('collections.errors.item_not_found')]);
        }

        return DB::transaction(function () use ($collection, $actor, $item): ContentCollectionItem {
            $existing = $collection->items()
                ->where('collectable_type', $item->getMorphClass())
                ->where('collectable_id', $item->getKey())
                ->first();
            if ($existing instanceof ContentCollectionItem) {
                return $existing;
            }

            $entry = $collection->items()->create([
                'organization_id' => $collection->organization_id,
                'collectable_type' => $item->getMorphClass(),
                'collectable_id' => (int) $item->getKey(),
                'position' => (int) $collection->items()->max('position') + 1,
                'added_by' => $actor->id,
            ]);
            $collection->audit('collection.item_added', [
                'type' => $this->types->keyFor($item),
                'item_id' => (int) $item->getKey(),
            ]);

            return $entry;
        });
    }

    /**
     * Legt einen Inhalt, den ein Systemablauf gerade selbst erzeugt hat, in die
     * gleichnamige Sammlung der obersten Ebene (bei Bedarf angelegt). Ohne
     * Sichtbarkeitsprüfung — nur für solche Abläufe, etwa den Known Error aus
     * dem Helpdesk, dessen Anleger kein Leserecht im Wissensarchiv braucht.
     */
    public function placeInNamedCollection(int $organizationId, string $title, Model $item, ?User $actor): ContentCollectionItem {
        return DB::transaction(function () use ($organizationId, $title, $item, $actor): ContentCollectionItem {
            $collection = ContentCollection::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereNull('parent_id')
                ->whereNull('archived_at')
                ->where('visibility', ContentCollection::VISIBILITY_ORGANIZATION)
                ->where('title', $title)
                ->first();
            if (! $collection instanceof ContentCollection) {
                $collection = ContentCollection::query()->create([
                    'organization_id' => $organizationId,
                    'parent_id' => null,
                    'title' => $title,
                    'visibility' => ContentCollection::VISIBILITY_ORGANIZATION,
                    'position' => (int) ContentCollection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('parent_id')->max('position') + 1,
                    'created_by' => $actor?->id,
                ]);
            }

            return $collection->items()->firstOrCreate([
                'collectable_type' => $item->getMorphClass(),
                'collectable_id' => (int) $item->getKey(),
            ], [
                'organization_id' => $organizationId,
                'position' => (int) $collection->items()->max('position') + 1,
                'added_by' => $actor?->id,
            ]);
        });
    }

    public function removeItem(ContentCollectionItem $entry): void {
        $collection = $entry->collection;
        $type = $this->types->keyForMorphType($entry->collectable_type);
        $itemId = $entry->collectable_id;
        $entry->delete();

        $collection?->audit('collection.item_removed', ['type' => $type, 'item_id' => $itemId]);
    }

    /**
     * Inhalte der Sammlung, soweit die Person sie sehen darf. Verborgene
     * Einträge fallen still weg — auch ihre Anzahl wird nicht verraten.
     *
     * @return list<array{entry: ContentCollectionItem, model: Model, type: string, title: string, url: string, icon: string, label: string, subject: \App\Services\Support\Content\ContentSubject}>
     */
    public function visibleItems(ContentCollection $collection, User $viewer): array {
        $entries = $collection->items()->with('adder:id,name')->orderBy('position')->get();

        $byType = [];
        foreach ($entries as $entry) {
            $key = $this->types->keyForMorphType($entry->collectable_type);
            if ($key !== null) {
                $byType[$key][] = $entry->collectable_id;
            }
        }

        $models = [];
        foreach ($byType as $key => $ids) {
            foreach ($this->types->visible($key, $viewer, array_values(array_unique($ids))) as $model) {
                $models[$key . ':' . $model->getKey()] = $model;
            }
        }

        $rows = [];
        foreach ($entries as $entry) {
            $key = $this->types->keyForMorphType($entry->collectable_type);
            $model = $key !== null ? ($models[$key . ':' . $entry->collectable_id] ?? null) : null;
            if ($key === null || $model === null) {
                continue;
            }
            $rows[] = [
                'entry' => $entry,
                'model' => $model,
                'type' => $key,
                'title' => $this->types->title($model),
                'url' => $this->types->url($model),
                'icon' => $this->types->icon($key),
                'label' => $this->types->label($key),
                'subject' => $this->subjects->resolve($model),
            ];
        }

        return $rows;
    }

    /**
     * Sammlungen, in denen ein Inhalt liegt (für die Detailseite des Inhalts).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ContentCollection>
     */
    public function collectionsContaining(Model $item, User $viewer) {
        return ContentCollection::query()
            ->visibleTo($viewer)
            ->whereNull('archived_at')
            ->whereHas('items', static fn ($q) => $q
                ->where('collectable_type', $item->getMorphClass())
                ->where('collectable_id', $item->getKey()))
            ->orderBy('title')
            ->get();
    }

    /**
     * Baum der sichtbaren Sammlungen als flache Liste in Anzeigereihenfolge.
     *
     * @return list<array{collection: ContentCollection, depth: int, count: int}>
     */
    public function tree(User $viewer, bool $withArchived = false): array {
        $all = ContentCollection::query()
            ->visibleTo($viewer)
            ->when(! $withArchived, static fn ($q) => $q->whereNull('archived_at'))
            ->withCount('items')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        $children = [];
        foreach ($all as $collection) {
            $children[(int) ($collection->parent_id ?? 0)][] = $collection;
        }
        $ids = $all->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $rows = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $children): void {
            foreach ($children[$parentId] ?? [] as $collection) {
                $rows[] = ['collection' => $collection, 'depth' => $depth, 'count' => (int) $collection->getAttribute('items_count')];
                $walk((int) $collection->id, $depth + 1);
            }
        };
        $walk(0, 1);

        // Kinder, deren Eltern nicht sichtbar sind (privat oder archiviert), als Wurzeln zeigen.
        foreach ($all as $collection) {
            if ($collection->parent_id !== null && ! in_array((int) $collection->parent_id, $ids, true)) {
                $rows[] = ['collection' => $collection, 'depth' => 1, 'count' => (int) $collection->getAttribute('items_count')];
                $walk((int) $collection->id, 2);
            }
        }

        return $rows;
    }

    /**
     * Die Sammlung und ihre sichtbaren, nicht archivierten Untersammlungen
     * (Recherche-Facette, Einstieg „Wissen“); leer, wenn die Person die
     * Sammlung nicht sehen darf.
     *
     * @return list<int>
     */
    public function visibleSubtreeIds(User $viewer, ?int $organizationId, int $collectionId): array {
        $visible = ContentCollection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->visibleTo($viewer)
            ->whereNull('archived_at')
            ->get(['id', 'parent_id']);

        $children = [];
        $known = [];
        foreach ($visible as $collection) {
            $known[(int) $collection->id] = true;
            $children[(int) ($collection->parent_id ?? 0)][] = (int) $collection->id;
        }
        if (! isset($known[$collectionId])) {
            return [];
        }

        $ids = [];
        $queue = [$collectionId];
        while ($queue !== []) {
            $id = array_shift($queue);
            $ids[] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $ids;
    }

    private function parent(Organization $organization, User $actor, ?int $parentId): ?ContentCollection {
        if ($parentId === null) {
            return null;
        }

        $parent = ContentCollection::query()
            ->where('organization_id', $organization->id)
            ->visibleTo($actor)
            ->whereNull('archived_at')
            ->find($parentId);
        if (! $parent instanceof ContentCollection) {
            throw ValidationException::withMessages(['parent_id' => (string) __('collections.errors.parent_invalid')]);
        }

        return $parent;
    }

    private function visibility(?string $visibility): string {
        return $visibility === ContentCollection::VISIBILITY_PRIVATE
            ? ContentCollection::VISIBILITY_PRIVATE
            : ContentCollection::VISIBILITY_ORGANIZATION;
    }
}
