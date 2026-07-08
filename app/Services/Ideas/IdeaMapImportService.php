<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Enums\Ideas\{IdeaMapVisibility, IdeaNodeColor};
use App\Models\{IdeaMap, IdeaNode, IdeaNodeLink, Organization, User};
use CommonToolkit\Helper\Data\XmlHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;

/**
 * Import einer Ideenlandkarte aus FreeMind/Freeplane (`.mm`) oder OPML
 * (Feature 054, MVP-138). XML wird XXE-gehärtet über das Toolkit gelesen
 * ({@see XmlHelper::safeLoadString}: `LIBXML_NONET`, keine Entity-Expansion).
 * Zusätzliche Ressourcengrenzen (Knotenzahl/Tiefe) gegen präparierte Dateien.
 * Die neue Karte gehört dem Importeur und ist privat.
 *
 * Format-Erkennung über das Wurzelelement: `<opml>` bzw. `<map>` (FreeMind).
 * Rückgabe des Parsers: verschachtelte `['title', 'note', 'children']`.
 */
class IdeaMapImportService {
    private const MAX_NODES = 5000;

    private const MAX_DEPTH = 60;

    public function import(Organization $organization, User $owner, string $content, ?string $filename = null): IdeaMap {
        $xml = XmlHelper::safeLoadString($content);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException((string) __('ideas.import.error.invalid'));
        }

        $tree = match ($xml->getName()) {
            'opml' => $this->parseOpml($xml),
            'map' => $this->parseFreeMind($xml),
            default => throw new RuntimeException((string) __('ideas.import.error.unsupported')),
        };
        if ($tree === null) {
            throw new RuntimeException((string) __('ideas.import.error.empty'));
        }

        $title = $tree['title'] !== '' ? $tree['title'] : ($this->baseName($filename) ?: (string) __('ideas.import.default_title'));

        return DB::transaction(function () use ($organization, $owner, $title, $tree): IdeaMap {
            $map = IdeaMap::query()->create([
                'organization_id' => $organization->id,
                'created_by' => $owner->id,
                'owner_user_id' => $owner->id,
                'title' => mb_substr($title, 0, 191),
                'visibility' => IdeaMapVisibility::Private->value,
            ]);

            $root = $map->nodes()->create([
                'organization_id' => $organization->id,
                'is_root' => true,
                'title' => mb_substr($title, 0, 500),
                'note' => $tree['note'],
                'color' => IdeaNodeColor::Default->value,
                'sort_order' => 0,
                'lock_version' => 1,
                'created_by' => $owner->id,
            ]);

            // Kontext für Querverbindungen (FreeMind <arrowlink>): externe
            // Knoten-IDs → erzeugte Knoten-IDs, plus gesammelte Kanten.
            $ctx = ['extIdToId' => [], 'arrows' => [], 'count' => 1];
            $this->registerNode($root->id, $tree, $ctx);

            $sort = 0;
            foreach ($tree['children'] as $child) {
                $this->createSubtree($map, $root, $child, $owner, 1, $sort++, $ctx);
            }

            $this->createArrows($map, $owner, $ctx);

            return $map;
        });
    }

    /**
     * @param  array<string, mixed>  $node  aus dem Parser (Shape title/note/ext_id/arrows/children)
     * @param  array{extIdToId: array<string, int>, arrows: list<array{source: int, to: string, label: ?string}>, count: int}  $ctx
     */
    private function createSubtree(IdeaMap $map, IdeaNode $parent, array $node, User $owner, int $depth, int $sortOrder, array &$ctx): void {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException((string) __('ideas.import.error.too_deep'));
        }
        if (++$ctx['count'] > self::MAX_NODES) {
            throw new RuntimeException((string) __('ideas.import.error.too_large'));
        }

        $title = trim((string) ($node['title'] ?? ''));
        $note = isset($node['note']) && is_string($node['note']) ? $node['note'] : null;
        $created = $map->nodes()->create([
            'organization_id' => $map->organization_id,
            'parent_id' => $parent->id,
            'title' => $title !== '' ? mb_substr($title, 0, 500) : '—',
            'note' => $note,
            'color' => IdeaNodeColor::Default->value,
            'sort_order' => $sortOrder,
            'lock_version' => 1,
            'created_by' => $owner->id,
        ]);
        $this->registerNode($created->id, $node, $ctx);

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $childSort = 0;
        foreach ($children as $child) {
            if (is_array($child)) {
                $this->createSubtree($map, $created, $child, $owner, $depth + 1, $childSort++, $ctx);
            }
        }
    }

    /**
     * Vermerkt die externe Knoten-ID (falls vorhanden) und die ausgehenden
     * Kanten des Knotens für die spätere Querverbindungs-Auflösung.
     *
     * @param  array<string, mixed>  $node
     * @param  array{extIdToId: array<string, int>, arrows: list<array{source: int, to: string, label: ?string}>, count: int}  $ctx
     */
    private function registerNode(int $nodeId, array $node, array &$ctx): void {
        $extId = isset($node['ext_id']) && is_string($node['ext_id']) && $node['ext_id'] !== '' ? $node['ext_id'] : null;
        if ($extId !== null) {
            $ctx['extIdToId'][$extId] = $nodeId;
        }

        $arrows = is_array($node['arrows'] ?? null) ? $node['arrows'] : [];
        foreach ($arrows as $arrow) {
            if (is_array($arrow) && isset($arrow['to']) && is_string($arrow['to'])) {
                $ctx['arrows'][] = [
                    'source' => $nodeId,
                    'to' => $arrow['to'],
                    'label' => isset($arrow['label']) && is_string($arrow['label']) && $arrow['label'] !== '' ? $arrow['label'] : null,
                ];
            }
        }
    }

    /**
     * Legt die gesammelten Querverbindungen an (FreeMind <arrowlink>): Ziel über
     * die externe ID auflösen, Selbstverweise/unauflösbare/Dubletten überspringen.
     *
     * @param  array{extIdToId: array<string, int>, arrows: list<array{source: int, to: string, label: ?string}>, count: int}  $ctx
     */
    private function createArrows(IdeaMap $map, User $owner, array $ctx): void {
        $seen = [];
        foreach ($ctx['arrows'] as $arrow) {
            $target = $ctx['extIdToId'][$arrow['to']] ?? null;
            $source = $arrow['source'];
            $key = $source . ':' . $target;
            if ($target === null || $target === $source || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            IdeaNodeLink::create([
                'organization_id' => $map->organization_id,
                'idea_map_id' => $map->id,
                'source_node_id' => $source,
                'target_node_id' => $target,
                'label' => $arrow['label'] !== null ? mb_substr($arrow['label'], 0, 120) : null,
                'color' => IdeaNodeColor::Default->value,
                'created_by' => $owner->id,
            ]);
        }
    }

    // ── OPML ────────────────────────────────────────────────────────────
    /** @return array{title: string, note: ?string, ext_id: ?string, arrows: list<array<string, mixed>>, children: list<array<string, mixed>>}|null */
    private function parseOpml(SimpleXMLElement $xml): ?array {
        $body = $xml->body ?? null;
        if ($body === null || $body->outline === null || count($body->outline) === 0) {
            return null;
        }

        $outlines = $body->outline;
        $first = $outlines[0] ?? null;
        if (count($outlines) === 1 && $first instanceof SimpleXMLElement) {
            return $this->opmlNode($first);
        }

        // Mehrere Top-Level-Outlines → synthetische Wurzel (head/title).
        $title = isset($xml->head->title) ? trim((string) $xml->head->title) : '';
        $children = [];
        foreach ($outlines as $outline) {
            $children[] = $this->opmlNode($outline);
        }

        return ['title' => $title, 'note' => null, 'ext_id' => null, 'arrows' => [], 'children' => $children];
    }

    /** @return array{title: string, note: ?string, ext_id: ?string, arrows: list<array<string, mixed>>, children: list<array<string, mixed>>} */
    private function opmlNode(SimpleXMLElement $outline): array {
        $attrs = $outline->attributes();
        $title = isset($attrs['text']) ? trim((string) $attrs['text']) : (isset($attrs['title']) ? trim((string) $attrs['title']) : '');
        $note = isset($attrs['_note']) ? trim((string) $attrs['_note']) : '';

        $children = [];
        foreach ($outline->outline as $child) {
            $children[] = $this->opmlNode($child);
        }

        // OPML kennt keine Standard-Querverbindungen → keine externen IDs/Kanten.
        return ['title' => $title, 'note' => $note !== '' ? $note : null, 'ext_id' => null, 'arrows' => [], 'children' => $children];
    }

    // ── FreeMind / Freeplane (.mm) ──────────────────────────────────────
    /** @return array{title: string, note: ?string, ext_id: ?string, arrows: list<array<string, mixed>>, children: list<array<string, mixed>>}|null */
    private function parseFreeMind(SimpleXMLElement $xml): ?array {
        $root = $xml->node[0] ?? null;
        if (! $root instanceof SimpleXMLElement) {
            return null;
        }

        return $this->freeMindNode($root);
    }

    /** @return array{title: string, note: ?string, ext_id: ?string, arrows: list<array<string, mixed>>, children: list<array<string, mixed>>} */
    private function freeMindNode(SimpleXMLElement $node): array {
        $attrs = $node->attributes();
        $title = isset($attrs['TEXT']) ? trim((string) $attrs['TEXT']) : '';
        $extId = isset($attrs['ID']) ? (string) $attrs['ID'] : null;

        // Notiz: <richcontent TYPE="NOTE"> … </richcontent> (HTML → Text).
        $note = null;
        foreach ($node->richcontent as $rc) {
            $rcAttrs = $rc->attributes();
            if (isset($rcAttrs['TYPE']) && (string) $rcAttrs['TYPE'] === 'NOTE') {
                $note = trim(strip_tags((string) $rc->asXML()));
                break;
            }
        }

        // Querverbindungen: <arrowlink DESTINATION="ID_…" MIDDLE_LABEL="…"/>.
        $arrows = [];
        foreach ($node->arrowlink as $arrowlink) {
            $alAttrs = $arrowlink->attributes();
            if (isset($alAttrs['DESTINATION'])) {
                $arrows[] = [
                    'to' => (string) $alAttrs['DESTINATION'],
                    'label' => isset($alAttrs['MIDDLE_LABEL']) ? trim((string) $alAttrs['MIDDLE_LABEL']) : null,
                ];
            }
        }

        $children = [];
        foreach ($node->node as $child) {
            $children[] = $this->freeMindNode($child);
        }

        return ['title' => $title, 'note' => $note !== null && $note !== '' ? $note : null, 'ext_id' => $extId, 'arrows' => $arrows, 'children' => $children];
    }

    private function baseName(?string $filename): string {
        if ($filename === null) {
            return '';
        }

        return trim(pathinfo($filename, PATHINFO_FILENAME));
    }
}
