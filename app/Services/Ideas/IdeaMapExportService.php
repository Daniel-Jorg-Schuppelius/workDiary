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
use CommonToolkit\Builders\XmlDocumentBuilder;
use CommonToolkit\Entities\XML\{Attribute, Element};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

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

    /**
     * OPML 2.0 (Standard-Gliederungsformat, öffnet in Freeplane/XMind/Outlinern).
     * XML wird über den Toolkit-`XmlDocumentBuilder` erzeugt (toolkit-first);
     * Notiz/Status als `_note`/`_status`-Attribute (OPML-Konvention für Extras).
     */
    public function opml(IdeaMap $map): string {
        $map->loadMissing(['nodes' => fn ($q) => $q->orderBy('sort_order')]);
        $byParent = $map->nodes->groupBy('parent_id');
        $root = $map->nodes->firstWhere('is_root', true);

        $head = new Element('head', null, null, null, [], [new Element('title', $map->title)]);
        $bodyChildren = $root !== null ? [$this->opmlOutline($root, $byParent)] : [];
        $body = new Element('body', null, null, null, [], $bodyChildren);

        return XmlDocumentBuilder::create('opml')
            ->withEncoding('UTF-8')
            ->withFormatOutput(true)
            ->addAttribute('version', '2.0')
            ->addElement($head)
            ->addElement($body)
            ->toString();
    }

    /**
     * @param  Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, IdeaNode>>  $byParent
     */
    private function opmlOutline(IdeaNode $node, Collection $byParent): Element {
        $attributes = [new Attribute('text', (string) $node->title)];
        if ($node->note !== null && $node->note !== '') {
            $attributes[] = new Attribute('_note', (string) $node->note);
        }
        if ($node->node_status !== null && $node->node_status !== '') {
            $attributes[] = new Attribute('_status', (string) $node->node_status);
        }

        $children = $byParent->get($node->id, collect())
            ->sortBy('sort_order')
            ->map(fn (IdeaNode $child): Element => $this->opmlOutline($child, $byParent))
            ->values()
            ->all();

        return new Element('outline', null, null, null, $attributes, $children);
    }

    /**
     * Markdown-Gliederung (eingerückte Liste). Titel als H1, Beschreibung als
     * Absatz, dann der Baum als verschachtelte Aufzählung; Status als Suffix,
     * Notiz eingerückt darunter. Reines workDiary-Format (kein Toolkit nötig).
     */
    public function markdown(IdeaMap $map): string {
        $map->loadMissing(['nodes' => fn ($q) => $q->orderBy('sort_order')]);
        $byParent = $map->nodes->groupBy('parent_id');
        $root = $map->nodes->firstWhere('is_root', true);

        $lines = ['# ' . $map->title, ''];
        if ($map->description !== null && $map->description !== '') {
            $lines[] = $map->description;
            $lines[] = '';
        }
        if ($root !== null) {
            $this->markdownNode($root, $byParent, 0, $lines);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param  Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, IdeaNode>>  $byParent
     * @param  list<string>  $lines
     */
    private function markdownNode(IdeaNode $node, Collection $byParent, int $depth, array &$lines): void {
        $indent = str_repeat('  ', $depth);
        $status = $node->node_status !== null && $node->node_status !== '' ? ' `' . $node->node_status . '`' : '';
        $lines[] = $indent . '- ' . $node->title . $status;
        if ($node->note !== null && $node->note !== '') {
            foreach (preg_split('/\R/', (string) $node->note) ?: [] as $noteLine) {
                $lines[] = $indent . '  > ' . $noteLine;
            }
        }

        foreach ($byParent->get($node->id, collect())->sortBy('sort_order') as $child) {
            $this->markdownNode($child, $byParent, $depth + 1, $lines);
        }
    }

    /** Gliederungs-PDF (pdf-toolkit PDFWriterRegistry). */
    public function pdf(IdeaMap $map): string {
        $map->loadMissing(['owner:id,name', 'nodes' => fn ($q) => $q->orderBy('sort_order')]);

        $html = View::make('pdf.idea-map', [
            'map' => $map,
            'byParent' => $map->nodes->groupBy('parent_id'),
            'root' => $map->nodes->firstWhere('is_root', true),
            'generatedAt' => now(),
        ])->render();

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (pdf.idea-map).');
    }
}
