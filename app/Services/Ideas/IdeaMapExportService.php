<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Models\{IdeaMap, IdeaNode};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Exporte einer Ideenlandkarte (Feature 054, MVP-110). Das JSON-Schema ist
 * dokumentiert und stabil (`format`-Feld, Sqids statt interner IDs); das PDF
 * ist bewusst die Gliederungsdarstellung (eingerückter Baum, Status/Farbe als
 * Text) — keine Canvas-Grafik im MVP. Beide Exporte werden vom Controller
 * auditiert und laufen nur über die `export`-Ability (Eigentümer).
 */
class IdeaMapExportService {
    public const FORMAT = 'workdiary.idea-map.v1';

    /** @return array<string, mixed> */
    public function toArray(IdeaMap $map): array {
        $map->loadMissing(['owner:id,name', 'nodes' => fn ($q) => $q->orderBy('sort_order'), 'nodes.references.target']);
        $byParent = $map->nodes->groupBy('parent_id');
        $root = $map->nodes->firstWhere('is_root', true);

        return [
            'format' => self::FORMAT,
            'map' => [
                'sqid' => $map->sqid,
                'title' => $map->title,
                'description' => $map->description,
                'visibility' => $map->visibility->value,
                'owner' => $map->owner?->name,
                'archived_at' => $map->archived_at?->toIso8601String(),
                'exported_at' => now()->toIso8601String(),
            ],
            'tree' => $root !== null ? $this->serializeNode($root, $byParent) : null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, IdeaNode>>  $byParent
     * @return array<string, mixed>
     */
    private function serializeNode(IdeaNode $node, $byParent): array {
        return [
            'sqid' => $node->sqid,
            'title' => $node->title,
            'note' => $node->note,
            'color' => $node->color->value,
            'status' => $node->node_status,
            'position' => ['x' => $node->pos_x, 'y' => $node->pos_y],
            'lock_version' => (int) $node->lock_version,
            'references' => $node->references->map(fn ($r): array => [
                'kind' => (string) $r->kind,
                'type' => class_basename((string) $r->target_type),
                'label' => (string) ($r->target?->getAttribute('title') ?? $r->target?->getAttribute('name') ?? '—'),
            ])->values()->all(),
            'children' => $byParent->get($node->id, collect())
                ->sortBy('sort_order')
                ->map(fn (IdeaNode $child): array => $this->serializeNode($child, $byParent))
                ->values()
                ->all(),
        ];
    }

    /** Gliederungs-PDF (DomPDF). */
    public function pdf(IdeaMap $map): string {
        $map->loadMissing(['owner:id,name', 'nodes' => fn ($q) => $q->orderBy('sort_order')]);

        $html = View::make('pdf.idea-map', [
            'map' => $map,
            'byParent' => $map->nodes->groupBy('parent_id'),
            'root' => $map->nodes->firstWhere('is_root', true),
            'generatedAt' => now(),
        ])->render();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return (string) $pdf->output();
    }
}
