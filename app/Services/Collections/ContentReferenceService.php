<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentReferenceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Ideas\{IdeaMap, IdeaNode};
use App\Models\Knowledge\{ContentReference, KnowledgeArticle};
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verweise und Rückverweise (MVP-811, Feature 155).
 *
 * Von Hand gesetzt werden nur Verweise der Art `mentioned` zwischen den Typen
 * aus {@see CollectableTypes}. Die Rückverweise einer Seite zeigen dagegen
 * alles, was auf sie zeigt — auch die Fachverweise aus Wissensbasis
 * (`linked`) und Ideenlandkarten (`linked`, `converted`). Wie bei Sammlungen
 * verleiht ein Verweis keinen Zugriff: jede Quelle erscheint nur, wenn die
 * Person sie ohnehin öffnen darf.
 */
class ContentReferenceService {
    /** Obergrenze der Auswahl je Typ im Dialog „Verweis setzen“. */
    public const PICKER_LIMIT = 200;

    public function __construct(private readonly CollectableTypes $types) {}

    public function add(Model $source, Model $target, User $actor): ContentReference {
        $sourceKey = $this->types->keyFor($source);
        $targetKey = $this->types->keyFor($target);
        if ($sourceKey === null || $targetKey === null) {
            throw ValidationException::withMessages(['target' => (string) __('collections.errors.type_not_allowed')]);
        }
        if ($source->getMorphClass() === $target->getMorphClass() && (int) $source->getKey() === (int) $target->getKey()) {
            throw ValidationException::withMessages(['target' => (string) __('collections.references.errors.self')]);
        }
        if ((int) $source->getAttribute('organization_id') !== (int) $target->getAttribute('organization_id')
            || ! $this->types->isVisibleTo($source, $actor)
            || ! $this->types->isVisibleTo($target, $actor)) {
            throw ValidationException::withMessages(['target' => (string) __('collections.errors.item_not_found')]);
        }

        return DB::transaction(function () use ($source, $target, $actor, $targetKey): ContentReference {
            $attributes = [
                'source_type' => $source->getMorphClass(),
                'source_id' => (int) $source->getKey(),
                'target_type' => $target->getMorphClass(),
                'target_id' => (int) $target->getKey(),
                'kind' => ContentReference::KIND_MENTIONED,
            ];
            $existing = ContentReference::query()->where($attributes)->first();
            if ($existing instanceof ContentReference) {
                return $existing;
            }

            $reference = ContentReference::query()->create([
                ...$attributes,
                'organization_id' => (int) $source->getAttribute('organization_id'),
                'created_by' => $actor->id,
            ]);
            $this->audit($source, 'content_reference.added', $targetKey, (int) $target->getKey());

            return $reference;
        });
    }

    /** Nur von Hand gesetzte Verweise lassen sich hier lösen; Fachverweise pflegt ihr Modul. */
    public function remove(ContentReference $reference, User $actor): void {
        if ($reference->kind !== ContentReference::KIND_MENTIONED) {
            throw ValidationException::withMessages(['reference' => (string) __('collections.references.errors.not_removable')]);
        }
        $source = $this->resolve($reference->source_type, $reference->source_id);
        if ($source === null || ! $this->types->isVisibleTo($source, $actor)) {
            throw ValidationException::withMessages(['reference' => (string) __('collections.errors.item_not_found')]);
        }

        DB::transaction(function () use ($reference, $source): void {
            $targetKey = $this->types->keyForMorphType($reference->target_type);
            $targetId = $reference->target_id;
            $reference->delete();
            $this->audit($source, 'content_reference.removed', $targetKey, $targetId);
        });
    }

    /**
     * Gesetzte Verweise dieses Inhalts und seine Überführungen (Notiz →
     * Artikel, MVP-813), soweit das Ziel sichtbar ist.
     *
     * @return list<array{reference: ContentReference, type: string, title: string, url: string, icon: string, label: string}>
     */
    public function outgoing(Model $source, User $viewer): array {
        $references = ContentReference::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereIn('kind', [ContentReference::KIND_MENTIONED, ContentReference::KIND_CONVERTED])
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($this->visibleModels($references->all(), 'target', $viewer) as [$reference, $key, $model, , $link]) {
            $rows[] = [
                'reference' => $reference,
                'type' => $key,
                'title' => $this->types->title($model),
                'url' => $this->types->url($link),
                'icon' => $this->types->icon($key),
                'label' => $this->types->label($key),
            ];
        }

        return $rows;
    }

    /**
     * Rückverweise: alle sichtbaren Quellen, die auf diesen Inhalt zeigen,
     * gruppiert nach Typ in der Reihenfolge aus {@see CollectableTypes::TYPES}.
     *
     * @param  bool  $withoutKnowledgeLinks  für Seiten, die Wissensverknüpfungen schon in der Wissenskarte zeigen
     * @return list<array{type: string, label: string, icon: string, items: list<array{reference: ContentReference, title: string, context: string|null, url: string, kind: string}>}>
     */
    public function backlinks(Model $target, User $viewer, bool $withoutKnowledgeLinks = false): array {
        $references = ContentReference::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->when($withoutKnowledgeLinks, static fn ($q) => $q->whereNot(static fn ($w) => $w
                ->where('source_type', (new KnowledgeArticle())->getMorphClass())
                ->where('kind', ContentReference::KIND_LINKED)))
            ->orderByDesc('id')
            ->get();

        $groups = [];
        foreach ($this->visibleModels($references->all(), 'source', $viewer) as [$reference, $key, $model, $context, $link]) {
            $groups[$key][] = [
                'reference' => $reference,
                'title' => $model instanceof IdeaNode ? trim($model->title) : $this->types->title($model),
                'context' => $context,
                'url' => $this->types->url($link),
                'kind' => $reference->kind,
            ];
        }

        $rows = [];
        foreach ($this->types->keys() as $key) {
            if (isset($groups[$key])) {
                $rows[] = ['type' => $key, 'label' => $this->types->label($key), 'icon' => $this->types->icon($key), 'items' => $groups[$key]];
            }
        }

        return $rows;
    }

    /**
     * Auswahl für „Verweis setzen“: sichtbare Inhalte je Typ, zuletzt
     * geänderte zuerst, ohne den Inhalt selbst und ohne bereits gesetzte Ziele.
     *
     * @return list<array{type: string, label: string, items: list<array{value: string, title: string}>}>
     */
    public function pickerOptions(Model $source, User $viewer): array {
        $taken = ContentReference::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('kind', ContentReference::KIND_MENTIONED)
            ->get(['target_type', 'target_id'])
            ->map(static fn (ContentReference $r): string => $r->target_type . ':' . $r->target_id)
            ->all();
        $taken[] = $source->getMorphClass() . ':' . $source->getKey();

        $groups = [];
        foreach ($this->types->keys() as $key) {
            $class = $this->types->classFor($key);
            if ($class === null || ! $this->types->typeAvailableTo($key, $viewer)) {
                continue;
            }
            $ids = $class::query()
                ->where('organization_id', $source->getAttribute('organization_id'))
                ->latest('updated_at')
                ->limit(self::PICKER_LIMIT * 2)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $items = [];
            foreach ($this->types->visible($key, $viewer, array_values($ids)) as $model) {
                if (in_array($model->getMorphClass() . ':' . $model->getKey(), $taken, true)) {
                    continue;
                }
                $items[] = ['value' => $key . ':' . Sqid::encode($model::class, (int) $model->getKey()), 'title' => $this->types->title($model)];
                if (count($items) >= self::PICKER_LIMIT) {
                    break;
                }
            }
            usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

            if ($items !== []) {
                $groups[] = ['type' => $key, 'label' => $this->types->label($key), 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Löst eine Seite der Verweise auf und behält nur, was die Person sehen
     * darf. Ideenknoten gelten über ihre Karte; sie erscheinen unter
     * „Ideenlandkarten“ mit dem Kartentitel als Kontext.
     *
     * @param  array<int, ContentReference>  $references
     * @param  'source'|'target'  $side
     * @return list<array{0: ContentReference, 1: string, 2: Model, 3: string|null, 4: Model}>
     */
    private function visibleModels(array $references, string $side, User $viewer): array {
        $nodeMorph = (new IdeaNode())->getMorphClass();

        $idsByKey = [];
        $nodeIds = [];
        foreach ($references as $reference) {
            $type = (string) $reference->getAttribute($side . '_type');
            $id = (int) $reference->getAttribute($side . '_id');
            if ($type === $nodeMorph) {
                $nodeIds[] = $id;
            } elseif (($key = $this->types->keyForMorphType($type)) !== null) {
                $idsByKey[$key][] = $id;
            }
        }

        $models = [];
        foreach ($idsByKey as $key => $ids) {
            foreach ($this->types->visible($key, $viewer, array_values(array_unique($ids))) as $model) {
                $models[$key . ':' . $model->getKey()] = $model;
            }
        }

        // Knoten zählen nur mit sichtbarer Karte.
        $nodes = [];
        if ($nodeIds !== []) {
            $candidates = IdeaNode::query()->with('map')->whereKey(array_values(array_unique($nodeIds)))->get();
            $mapIds = $candidates->pluck('idea_map_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
            $visibleMaps = [];
            foreach ($this->types->visible('idea_map', $viewer, array_values($mapIds)) as $map) {
                $visibleMaps[(int) $map->getKey()] = true;
            }
            foreach ($candidates as $node) {
                if (isset($visibleMaps[(int) $node->idea_map_id]) && $node->map instanceof IdeaMap) {
                    $nodes[(int) $node->id] = [$node, $node->map];
                }
            }
        }

        $rows = [];
        foreach ($references as $reference) {
            $type = (string) $reference->getAttribute($side . '_type');
            $id = (int) $reference->getAttribute($side . '_id');
            if ($type === $nodeMorph) {
                if (isset($nodes[$id])) {
                    [$node, $map] = $nodes[$id];
                    $rows[] = [$reference, 'idea_map', $node, $map->title, $map];
                }

                continue;
            }
            $key = $this->types->keyForMorphType($type);
            if ($key !== null && isset($models[$key . ':' . $id])) {
                $rows[] = [$reference, $key, $models[$key . ':' . $id], null, $models[$key . ':' . $id]];
            }
        }

        return $rows;
    }

    private function resolve(string $morphType, int $id): ?Model {
        $key = $this->types->keyForMorphType($morphType);
        $class = $key !== null ? $this->types->classFor($key) : null;

        return $class !== null ? $class::query()->find($id) : null;
    }

    private function audit(Model $source, string $event, ?string $targetKey, int $targetId): void {
        if (method_exists($source, 'audit')) {
            $source->audit($event, ['type' => $targetKey, 'item_id' => $targetId]);
        }
    }
}
