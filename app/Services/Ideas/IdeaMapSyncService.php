<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Enums\Ideas\IdeaNodeColor;
use App\Exceptions\IdeaMapConflictException;
use App\Models\{IdeaMap, IdeaNode, IdeaNodeLink, IdeaNodeSummary, User};
use App\Services\SqidEncoder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Whole-Map-Sync des Canvas (Feature 054, MVP-136): Der Canvas (SimpleMindMap)
 * arbeitet auf der ganzen Karte und schickt beim Speichern den kompletten Baum.
 * Diese Klasse gleicht ihn gegen die normalisierten `idea_nodes` ab — **die DB
 * bleibt die Wahrheit** (Sqid ist die stabile Identität, damit Überführungs-
 * Referenzen und Audit den Umbau überleben).
 *
 * Ablauf (in einer Transaktion):
 *  1. karten-weite optimistische Sperre (`idea_maps.lock_version`) atomar
 *     inkrementieren; 0 betroffene Zeilen → {@see IdeaMapConflictException} (409);
 *  2. eingehenden Baum rekursiv durchlaufen: bekannte Knoten (per Sqid)
 *     aktualisieren, neue anlegen (Sqid zurückgeben), Reihenfolge/Position/
 *     Elternschaft aus der Baumstruktur übernehmen;
 *  3. aktive Knoten, die im Baum fehlen, wiederherstellbar soft-löschen;
 *  4. auf Kartenebene auditieren.
 *
 * Cross-Tenant-fest: eingehende Sqids müssen zu **dieser** Karte gehören, sonst
 * Abbruch. Zyklen sind durch die Baumstruktur ausgeschlossen (jeder Knoten
 * erscheint genau einmal; Doppelvorkommen wird abgewiesen).
 */
class IdeaMapSyncService {
    public function __construct(private readonly SqidEncoder $sqids) {}

    /** Felder, die aus dem Baum je Knoten übernommen werden. */
    private const NODE_FIELDS = ['title', 'note', 'color', 'node_status', 'pos_x', 'pos_y', 'parent_id', 'sort_order'];

    /**
     * @param  array<string, mixed>  $tree  Wurzelknoten mit rekursiven `children`
     * @param  array<int, mixed>|null  $links  Querverbindungen (MVP-137, untrusted); `null` = unverändert lassen
     * @param  array<int, mixed>|null  $summaries  Boundaries (MVP-137, untrusted); `null` = unverändert lassen
     * @return array{lock_version: int, created: array<string, string>}
     */
    public function sync(IdeaMap $map, array $tree, ?array $links, ?array $summaries, int $expectedVersion, ?User $actor = null): array {
        return DB::transaction(function () use ($map, $tree, $links, $summaries, $expectedVersion, $actor): array {
            // 1) Karten-Lock atomar: nur wenn die geladene Version noch aktuell ist.
            $bumped = IdeaMap::query()
                ->whereKey($map->id)
                ->where('lock_version', $expectedVersion)
                ->update(['lock_version' => DB::raw('lock_version + 1')]);
            if ($bumped === 0) {
                throw new IdeaMapConflictException($map->fresh() ?? $map);
            }

            /** @var array<int, IdeaNode> $existing  aktive Knoten der Karte, nach id */
            $existing = $map->nodes()->get()->keyBy('id')->all();

            $root = $this->resolveRoot($tree, $existing);

            $ctx = ['seen' => [], 'created' => [], 'updated' => 0, 'createdCount' => 0, 'actor' => $actor, 'refToId' => []];

            // Wurzel: nur Inhalt aktualisieren, nie Elternschaft/Reihenfolge.
            $this->applyFields($root, $this->fields($tree, parentId: null, sortOrder: (int) $root->sort_order), $ctx);
            $ctx['seen'][$root->id] = true;
            if (is_string($tree['sqid'] ?? null)) {
                $ctx['refToId'][$tree['sqid']] = $root->id;
            }
            $this->walkChildren($map, $tree, $root->id, $existing, $ctx);

            // 3) Fehlende (im Baum nicht mehr vorhandene) Knoten soft-löschen.
            //    Der Baum lässt ganze Teilbäume weg → jeder Nachfahre fehlt
            //    ebenfalls, ein flaches Löschen der Differenz genügt.
            $deleted = 0;
            foreach ($existing as $id => $node) {
                if ($node->is_root || isset($ctx['seen'][$id])) {
                    continue;
                }
                $node->delete();
                $deleted++;
            }

            // 4) Querverbindungen (MVP-137) — erst nach der Knoten-Phase, damit
            //    neue Knoten (client_id → id) als Endpunkte auflösbar sind.
            //    `null` = der Client sendet (noch) keine Links → unverändert lassen.
            $links === null ? null : $this->syncLinks($map, $links, $ctx['refToId'], $actor);

            // 5) Boundaries/Zusammenfassungen (MVP-137) — ebenfalls nach der
            //    Knoten-Phase; Elternknoten über refToId auflösbar.
            $summaries === null ? null : $this->syncSummaries($map, $summaries, $ctx['refToId'], $actor);

            $map->audit('idea_map.synced', [
                'created' => $ctx['createdCount'],
                'updated' => $ctx['updated'],
                'deleted' => $deleted,
            ]);

            return ['lock_version' => $expectedVersion + 1, 'created' => $ctx['created']];
        });
    }

    /**
     * Gleicht die Querverbindungen der Karte gegen den Payload ab. `from`/`to`
     * referenzieren Knoten per Sqid (bestehend) oder client_id (neu) — beide
     * werden über `$refToId` (aus der Knoten-Phase) auf reale, **map-eigene**
     * Knoten-IDs aufgelöst; unauflösbare oder Selbstverweise werden übersprungen.
     * Nicht mehr enthaltene Links werden gelöscht.
     *
     * @param  array<int, mixed>  $links  untrusted (Request-Eingabe)
     * @param  array<string, int>  $refToId
     */
    private function syncLinks(IdeaMap $map, array $links, array $refToId, ?User $actor): void {
        $seen = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $from = $refToId[(string) ($link['from'] ?? '')] ?? null;
            $to = $refToId[(string) ($link['to'] ?? '')] ?? null;
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $label = isset($link['label']) && is_string($link['label']) && $link['label'] !== ''
                ? mb_substr($link['label'], 0, 120) : null;
            $color = (IdeaNodeColor::tryFrom((string) ($link['color'] ?? '')) ?? IdeaNodeColor::Default)->value;

            $model = IdeaNodeLink::firstOrNew(['source_node_id' => $from, 'target_node_id' => $to]);
            if (! $model->exists) {
                $model->organization_id = $map->organization_id;
                $model->idea_map_id = $map->id;
                $model->created_by = $actor?->id;
            }
            $model->label = $label;
            $model->color = $color;
            $model->save();

            $seen[$from . ':' . $to] = true;
        }

        foreach ($map->links()->get() as $existingLink) {
            if (! isset($seen[$existingLink->source_node_id . ':' . $existingLink->target_node_id])) {
                $existingLink->delete();
            }
        }
    }

    /**
     * Gleicht die Boundaries/Zusammenfassungen ab. Eine Boundary klammert die
     * Kinder `start`..`end` eines Knotens (Elternknoten per Sqid/client_id über
     * `$refToId` auflösbar). Identität = (parent, start, end); nur das Label
     * wird ggf. aktualisiert. Nicht mehr enthaltene werden gelöscht. Ungültige
     * Bereiche (start<0, end<start, unauflösbarer Elternknoten) übersprungen.
     *
     * @param  array<int, mixed>  $summaries  untrusted (Request-Eingabe)
     * @param  array<string, int>  $refToId
     */
    private function syncSummaries(IdeaMap $map, array $summaries, array $refToId, ?User $actor): void {
        $seen = [];
        foreach ($summaries as $summary) {
            if (! is_array($summary)) {
                continue;
            }
            $parent = $refToId[(string) ($summary['parent'] ?? '')] ?? null;
            $start = $this->intOrNull($summary['start'] ?? null);
            $end = $this->intOrNull($summary['end'] ?? null);
            if ($parent === null || $start === null || $end === null || $start < 0 || $end < $start) {
                continue;
            }

            $label = isset($summary['label']) && is_string($summary['label']) && $summary['label'] !== ''
                ? mb_substr($summary['label'], 0, 120) : null;

            $model = IdeaNodeSummary::firstOrNew(['parent_node_id' => $parent, 'start_index' => $start, 'end_index' => $end]);
            if (! $model->exists) {
                $model->organization_id = $map->organization_id;
                $model->idea_map_id = $map->id;
                $model->created_by = $actor?->id;
            }
            $model->label = $label;
            $model->save();

            $seen[$parent . ':' . $start . ':' . $end] = true;
        }

        foreach ($map->summaries()->get() as $existing) {
            if (! isset($seen[$existing->parent_node_id . ':' . $existing->start_index . ':' . $existing->end_index])) {
                $existing->delete();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, IdeaNode>  $existing
     */
    private function resolveRoot(array $tree, array $existing): IdeaNode {
        $sqid = is_string($tree['sqid'] ?? null) ? $tree['sqid'] : '';
        $id = $this->sqids->decode(IdeaNode::class, $sqid);
        $root = $id !== null ? ($existing[$id] ?? null) : null;
        if (! $root instanceof IdeaNode || ! $root->is_root) {
            throw new RuntimeException((string) __('ideas.error.foreign_node'));
        }

        return $root;
    }

    /**
     * @param  array<string, mixed>  $parentNode
     * @param  array<int, IdeaNode>  $existing
     * @param  array{seen: array<int, true>, created: array<string, string>, updated: int, createdCount: int, actor: ?User, refToId: array<string, int>}  $ctx
     */
    private function walkChildren(IdeaMap $map, array $parentNode, int $parentId, array $existing, array &$ctx): void {
        $children = is_array($parentNode['children'] ?? null) ? $parentNode['children'] : [];
        $sort = 0;
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            $node = $this->resolveOrCreate($map, $child, $parentId, $sort, $existing, $ctx);
            $ctx['seen'][$node->id] = true;
            $this->walkChildren($map, $child, $node->id, $existing, $ctx);
            $sort++;
        }
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<int, IdeaNode>  $existing
     * @param  array{seen: array<int, true>, created: array<string, string>, updated: int, createdCount: int, actor: ?User, refToId: array<string, int>}  $ctx
     */
    private function resolveOrCreate(IdeaMap $map, array $incoming, int $parentId, int $sortOrder, array $existing, array &$ctx): IdeaNode {
        $sqid = is_string($incoming['sqid'] ?? null) && $incoming['sqid'] !== '' ? $incoming['sqid'] : null;

        if ($sqid !== null) {
            $id = $this->sqids->decode(IdeaNode::class, $sqid);
            $node = $id !== null ? ($existing[$id] ?? null) : null;
            if (! $node instanceof IdeaNode) {
                throw new RuntimeException((string) __('ideas.error.foreign_node'));
            }
            if (isset($ctx['seen'][$node->id])) {
                throw new RuntimeException((string) __('ideas.error.foreign_node')); // Doppelvorkommen im Baum
            }
            $this->applyFields($node, $this->fields($incoming, $parentId, $sortOrder), $ctx);
            $ctx['refToId'][$sqid] = $node->id; // Endpunkt-Auflösung für Links

            return $node;
        }

        // Neuer Knoten: anlegen, Sqid für die Antwort merken (client_id → sqid).
        $fields = $this->fields($incoming, $parentId, $sortOrder);
        $node = new IdeaNode();
        $node->forceFill($fields + [
            'organization_id' => $map->organization_id,
            'idea_map_id' => $map->id,
            'is_root' => false,
            'lock_version' => 1,
            'created_by' => $ctx['actor']?->id,
        ]);
        $node->save();
        $ctx['createdCount']++;
        $existing[$node->id] = $node; // für ggf. spätere Referenzen im selben Lauf

        $clientId = is_string($incoming['client_id'] ?? null) ? $incoming['client_id'] : null;
        if ($clientId !== null) {
            $ctx['created'][$clientId] = $node->sqid;
            $ctx['refToId'][$clientId] = $node->id; // Endpunkt-Auflösung für Links
        }

        return $node;
    }

    /**
     * Normalisiert die Knotenfelder aus dem eingehenden Baum (Sanitizing).
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function fields(array $incoming, ?int $parentId, int $sortOrder): array {
        $title = trim((string) ($incoming['title'] ?? ''));
        $status = $incoming['node_status'] ?? null;

        return [
            'title' => $title === '' ? '—' : mb_substr($title, 0, 500),
            'note' => isset($incoming['note']) && $incoming['note'] !== '' ? (string) $incoming['note'] : null,
            'color' => (IdeaNodeColor::tryFrom((string) ($incoming['color'] ?? '')) ?? IdeaNodeColor::Default)->value,
            'node_status' => is_string($status) && $status !== '' ? mb_substr($status, 0, 24) : null,
            'pos_x' => $this->intOrNull($incoming['pos_x'] ?? null),
            'pos_y' => $this->intOrNull($incoming['pos_y'] ?? null),
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Schreibt geänderte Felder eines bestehenden Knotens (nur bei echter
     * Abweichung), setzt `updated_by` und inkrementiert die per-Knoten-
     * `lock_version` — so erkennt auch die Gliederung eine vom Canvas geänderte
     * Zeile als Konflikt.
     *
     * @param  array<string, mixed>  $fields
     * @param  array{seen: array<int, true>, created: array<string, string>, updated: int, createdCount: int, actor: ?User, refToId: array<string, int>}  $ctx
     */
    private function applyFields(IdeaNode $node, array $fields, array &$ctx): void {
        // Wurzel behält Elternschaft/Reihenfolge (parent_id NULL, sort_order fix).
        if ($node->is_root) {
            unset($fields['parent_id'], $fields['sort_order']);
        }

        $dirty = [];
        foreach ($fields as $key => $value) {
            if (! in_array($key, self::NODE_FIELDS, true)) {
                continue;
            }
            $current = $key === 'color' ? $node->color->value : $node->getAttribute($key);
            if ($current !== $value) {
                $dirty[$key] = $value;
            }
        }
        if ($dirty === []) {
            return;
        }

        $node->forceFill($dirty + [
            'updated_by' => $ctx['actor']?->id,
            'lock_version' => (int) $node->lock_version + 1,
        ])->save();
        $ctx['updated']++;
    }

    private function intOrNull(mixed $value): ?int {
        return is_numeric($value) ? (int) $value : null;
    }
}
