<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Ideas\IdeaNodeColor;
use App\Exceptions\IdeaNodeConflictException;
use App\Models\{IdeaMap, IdeaNode, IdeaNodeReference, KnowledgeArticle, Project, Task, User};
use App\Services\Ideas\{IdeaNodeService, NodeConversionService};
use App\Services\SqidEncoder;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Knotenbezogene Editor-API der Ideenlandkarten (Feature 054, MVP-106/108):
 * kleine JSON-Operationen je Knoten — nie „ganze Karte speichern". Jede
 * Response trägt den neuen `lock_version`-Stand; ein veralteter Stand liefert
 * HTTP 409 mit dem aktuellen Serverknoten (sichtbarer Konflikt statt stillem
 * Last-write-wins). Sichtbarkeit delegiert vollständig an die IdeaMapPolicy;
 * archivierte Karten lehnen Mutationen ab (Policy::update).
 */
class IdeaNodeController extends Controller {
    public function __construct(private readonly IdeaNodeService $nodes) {}

    /** Kompletter Baum als ein JSON-Payload (Ladezeitpunkt des Editors). */
    public function tree(IdeaMap $map): JsonResponse {
        Gate::authorize('view', $map);

        $nodes = $map->nodes()->with('references.target')->orderBy('sort_order')->get();

        return response()->json([
            'map' => ['sqid' => $map->sqid, 'title' => $map->title, 'archived' => $map->isArchived()],
            'can_update' => Gate::allows('update', $map),
            'nodes' => $nodes->map(fn (IdeaNode $node): array => $this->serialize($node))->values(),
        ]);
    }

    public function store(Request $request, IdeaMap $map): JsonResponse {
        Gate::authorize('update', $map);

        $data = $request->validate([
            'parent' => ['required', 'string'],
            'title' => ['required', 'string', 'max:500'],
        ]);

        $parent = $this->resolveNode($map, (string) $data['parent']);

        try {
            $node = $this->nodes->create($map, $parent, (string) $data['title'], Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['node' => $this->serialize($node)], 201);
    }

    public function update(Request $request, IdeaMap $map, IdeaNode $node): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:500'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'color' => ['sometimes', Rule::enum(IdeaNodeColor::class)],
            'node_status' => ['sometimes', 'nullable', 'string', 'max:24'],
            'pos_x' => ['sometimes', 'nullable', 'integer', 'min:-100000', 'max:100000'],
            'pos_y' => ['sometimes', 'nullable', 'integer', 'min:-100000', 'max:100000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $node = $this->nodes->update(
                $node,
                $data,
                expectedVersion: (int) $data['lock_version'],
                actor: Auth::user(),
            );
        } catch (IdeaNodeConflictException $e) {
            // MVP-108: sichtbarer Konflikt mit aktuellem Serverstand.
            return response()->json([
                'message' => $e->getMessage(),
                'current' => $this->serialize($e->currentNode),
            ], 409);
        }

        return response()->json(['node' => $this->serialize($node)]);
    }

    public function move(Request $request, IdeaMap $map, IdeaNode $node): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        $data = $request->validate(['parent' => ['required', 'string']]);
        $parent = $this->resolveNode($map, (string) $data['parent']);

        try {
            $node = $this->nodes->move($node, $parent, Auth::user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['node' => $this->serialize($node)]);
    }

    /** Ordnet die Kinder eines Elternknotens neu (vollständige Sqid-Liste in Zielreihenfolge). */
    public function reorder(Request $request, IdeaMap $map, IdeaNode $node): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        $data = $request->validate([
            'children' => ['required', 'array'],
            'children.*' => ['string'],
        ]);

        $encoder = app(SqidEncoder::class);
        $ids = [];
        foreach ($data['children'] as $sqid) {
            $id = $encoder->decode(IdeaNode::class, (string) $sqid);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $this->nodes->reorder($node, $ids);

        return response()->json(['ok' => true]);
    }

    public function destroy(IdeaMap $map, IdeaNode $node): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        try {
            $this->nodes->deleteSubtree($node);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** Stellt einen gelöschten Teilbaum wieder her (SoftDeleted bindet nicht implizit). */
    public function restore(IdeaMap $map, string $nodeSqid): JsonResponse {
        Gate::authorize('update', $map);

        $id = app(SqidEncoder::class)->decode(IdeaNode::class, $nodeSqid);
        $node = $id !== null ? IdeaNode::onlyTrashed()->where('idea_map_id', $map->id)->find($id) : null;
        abort_unless($node instanceof IdeaNode, 404);

        $this->nodes->restoreSubtree($node);

        return response()->json(['node' => $this->serialize($node->refresh())]);
    }

    /**
     * Überführt den Knoten in ein Zielmodul (MVP-109): Aufgabe/Kanban,
     * Projekt oder Wissensartikel-Entwurf. Idempotent je Zieltyp — ein
     * zweiter Versuch liefert `existing: true` mit Link aufs bestehende Ziel.
     */
    public function convert(Request $request, IdeaMap $map, IdeaNode $node, NodeConversionService $conversions): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        $data = $request->validate(['target' => ['required', 'in:task,project,knowledge']]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $reference = match ((string) $data['target']) {
                'task' => $conversions->convertToTask($node, $actor),
                'project' => $conversions->convertToProject($node, $actor),
                default => $conversions->convertToKnowledgeArticle($node, $actor),
            };
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'reference' => $this->serializeReference($reference),
            'existing' => ! $reference->wasRecentlyCreated,
        ], $reference->wasRecentlyCreated ? 201 : 200);
    }

    /** Verweist den Knoten auf einen bestehenden Kunden/Projekt/Auftrag (kind = linked). */
    public function link(Request $request, IdeaMap $map, IdeaNode $node, NodeConversionService $conversions): JsonResponse {
        Gate::authorize('update', $map);
        $this->assertNodeOfMap($map, $node);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(NodeConversionService::LINKABLE_MAP))],
            'id' => ['required', 'string'],
        ]);

        $targetClass = NodeConversionService::LINKABLE_MAP[(string) $data['type']];
        $targetId = app(SqidEncoder::class)->decode($targetClass, (string) $data['id']);
        $target = $targetId !== null ? $targetClass::query()->find($targetId) : null;
        abort_unless($target !== null, 404);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $reference = $conversions->linkTo($node, $target, $actor);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['reference' => $this->serializeReference($reference)], 201);
    }

    /** @return array<string, mixed> */
    private function serializeReference(IdeaNodeReference $reference): array {
        $target = $reference->target;
        $label = $target?->getAttribute('title') ?? $target?->getAttribute('name') ?? '—';
        $url = match (true) {
            $target instanceof Project => route('projects.show', $target),
            $target instanceof KnowledgeArticle => route('knowledge.show', $target),
            $target instanceof Task => route('kanban.index'),
            $target instanceof \App\Models\Customer => route('customers.show', $target),
            $target instanceof \App\Models\DiaryEntry => route('diary.show', $target),
            default => null,
        };

        return [
            'kind' => (string) $reference->kind,
            'type' => class_basename((string) $reference->target_type),
            'label' => (string) $label,
            'url' => $url,
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(IdeaNode $node): array {
        return [
            'sqid' => $node->sqid,
            'parent' => $node->parent_id !== null
                ? app(SqidEncoder::class)->encode(IdeaNode::class, (int) $node->parent_id)
                : null,
            'is_root' => (bool) $node->is_root,
            'title' => $node->title,
            'note' => $node->note,
            'color' => $node->color->value,
            'node_status' => $node->node_status,
            'pos_x' => $node->pos_x,
            'pos_y' => $node->pos_y,
            'sort_order' => (int) $node->sort_order,
            'lock_version' => (int) $node->lock_version,
            'references' => $node->references->map(fn (IdeaNodeReference $r): array => $this->serializeReference($r))->values()->all(),
        ];
    }

    private function resolveNode(IdeaMap $map, string $sqid): IdeaNode {
        $id = app(SqidEncoder::class)->decode(IdeaNode::class, $sqid);
        $node = $id !== null ? IdeaNode::query()->where('idea_map_id', $map->id)->find($id) : null;
        abort_unless($node instanceof IdeaNode, 404);

        return $node;
    }

    private function assertNodeOfMap(IdeaMap $map, IdeaNode $node): void {
        abort_unless((int) $node->idea_map_id === (int) $map->id, 404);
    }
}
