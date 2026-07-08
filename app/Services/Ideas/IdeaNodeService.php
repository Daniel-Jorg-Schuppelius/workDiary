<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Enums\Ideas\IdeaNodeColor;
use App\Exceptions\IdeaNodeConflictException;
use App\Models\{IdeaMap, IdeaNode, User};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Knotenoperationen einer Ideenlandkarte (Feature 054, MVP-105/106/108):
 * kleine, knotenbezogene Mutationen (nie „ganze Karte speichern"), Zyklus-Guard
 * beim Verschieben, wiederherstellbares Teilbaum-Löschen und die optimistische
 * Sperre über `lock_version` — ein veralteter Stand führt zur
 * {@see IdeaNodeConflictException} (HTTP 409 im Editor), nie zu stillem
 * Last-write-wins.
 */
class IdeaNodeService {
    /** Legt einen Knoten unter dem Elternknoten an (ans Ende der Geschwister). */
    public function create(IdeaMap $map, IdeaNode $parent, string $title, ?User $actor = null): IdeaNode {
        $this->assertNodeBelongsToMap($map, $parent);

        return DB::transaction(function () use ($map, $parent, $title, $actor): IdeaNode {
            $sort = (int) $map->nodes()->where('parent_id', $parent->id)->max('sort_order');

            return $map->nodes()->create([
                'organization_id' => $map->organization_id,
                'parent_id' => $parent->id,
                'title' => $title,
                'color' => IdeaNodeColor::Default->value,
                'sort_order' => $sort + 1,
                // Explizit setzen (statt auf den DB-Default zu vertrauen): sonst
                // trägt die zurückgegebene In-Memory-Instanz lock_version = null,
                // der Editor sendet 0, und die erste Mutation scheitert an
                // `min:1` (HTTP 422). Muss dem Migrations-Default entsprechen.
                'lock_version' => 1,
                'created_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Aktualisiert Knotenfelder mit optimistischer Sperre: `$expectedVersion`
     * muss dem geladenen Stand entsprechen (atomarer UPDATE-Guard); bei
     * Abweichung fliegt {@see IdeaNodeConflictException} mit dem aktuellen
     * Serverstand. `null` überspringt den Guard (interne Aufrufe).
     *
     * @param  array<string, mixed>  $attributes  erlaubte Felder: title, note, color, node_status, pos_x, pos_y
     */
    public function update(IdeaNode $node, array $attributes, ?int $expectedVersion = null, ?User $actor = null): IdeaNode {
        $allowed = array_intersect_key($attributes, array_flip(['title', 'note', 'color', 'node_status', 'pos_x', 'pos_y']));
        $allowed['updated_by'] = $actor?->id;
        $allowed['updated_at'] = now();

        $query = IdeaNode::query()->whereKey($node->id);
        if ($expectedVersion !== null) {
            $query->where('lock_version', $expectedVersion);
        }

        $updated = $query->update($allowed + ['lock_version' => DB::raw('lock_version + 1')]);
        if ($updated === 0) {
            throw new IdeaNodeConflictException($node->fresh() ?? $node);
        }

        $node->refresh();

        // Das atomare Query-Update feuert keine Eloquent-Events — Verlauf
        // (MVP-108) daher explizit schreiben. Reine Positionsänderungen
        // (Canvas-Drag) bleiben bewusst außen vor (Rauschen).
        $audited = array_intersect_key($allowed, array_flip(['title', 'note', 'color', 'node_status']));
        if ($audited !== []) {
            $node->audit('updated', ['after' => $audited]);
        }

        return $node;
    }

    /** Hängt den Knoten unter einen neuen Elternknoten (Zyklus-Guard; Wurzel bleibt Wurzel). */
    public function move(IdeaNode $node, IdeaNode $newParent, ?User $actor = null): IdeaNode {
        $this->assertNodeBelongsToMap($node->map()->firstOrFail(), $newParent);

        if ($node->is_root) {
            throw new RuntimeException((string) __('ideas.error.root_immovable'));
        }
        if ($newParent->id === $node->id || $this->isDescendantOf($newParent, $node)) {
            throw new RuntimeException((string) __('ideas.error.cycle'));
        }

        return DB::transaction(function () use ($node, $newParent, $actor): IdeaNode {
            $sort = (int) IdeaNode::query()->where('parent_id', $newParent->id)->max('sort_order');
            $node->forceFill([
                'parent_id' => $newParent->id,
                'sort_order' => $sort + 1,
                'updated_by' => $actor?->id,
            ])->save();
            $node->audit('idea_node.moved', ['parent_id' => $newParent->id]);

            return $node;
        });
    }

    /**
     * Ordnet die Kinder eines Elternknotens neu (vollständige Liste der
     * Kind-IDs in Zielreihenfolge; fehlende/​fremde IDs werden ignoriert).
     *
     * @param  list<int>  $orderedChildIds
     */
    public function reorder(IdeaNode $parent, array $orderedChildIds): void {
        DB::transaction(function () use ($parent, $orderedChildIds): void {
            $children = $parent->children()->pluck('id', 'id');
            $sort = 0;
            foreach ($orderedChildIds as $childId) {
                if (! $children->has((int) $childId)) {
                    continue;
                }
                IdeaNode::query()->whereKey((int) $childId)->update(['sort_order' => $sort++]);
            }
        });
    }

    /** Löscht den Knoten samt Teilbaum wiederherstellbar (SoftDeletes, rekursiv). */
    public function deleteSubtree(IdeaNode $node): void {
        if ($node->is_root) {
            throw new RuntimeException((string) __('ideas.error.root_immovable'));
        }

        DB::transaction(function () use ($node): void {
            foreach ($node->children()->get() as $child) {
                $this->deleteSubtree($child);
            }
            $node->delete();
        });
    }

    /** Stellt einen gelöschten Knoten samt Teilbaum wieder her. */
    public function restoreSubtree(IdeaNode $node): void {
        DB::transaction(function () use ($node): void {
            // Hängt der Elternknoten selbst im Papierkorb, wird unter der Wurzel
            // wiederhergestellt (kein Restore in einen unsichtbaren Zweig).
            $parent = $node->parent()->withTrashed()->first();
            if ($parent !== null && $parent->trashed()) {
                $root = $node->map()->firstOrFail()->rootNode()->firstOrFail();
                $node->forceFill(['parent_id' => $root->id]);
            }
            $node->restore();

            foreach (IdeaNode::onlyTrashed()->where('parent_id', $node->id)->get() as $child) {
                $this->restoreSubtree($child);
            }
        });
    }

    /** Ist $node ein Nachfahre von $ancestor? (Zyklus-Guard beim Verschieben.) */
    private function isDescendantOf(IdeaNode $node, IdeaNode $ancestor): bool {
        $current = $node->parent()->first();
        $guard = 0;
        while ($current !== null && $guard < 1000) {
            if ($current->id === $ancestor->id) {
                return true;
            }
            $current = $current->parent()->first();
            $guard++;
        }

        return false;
    }

    private function assertNodeBelongsToMap(IdeaMap $map, IdeaNode $node): void {
        if ((int) $node->idea_map_id !== (int) $map->id) {
            throw new RuntimeException((string) __('ideas.error.foreign_node'));
        }
    }
}
