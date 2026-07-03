<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebDaXmlParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\BoqItemType;
use CommonToolkit\Helper\Data\{NumberHelper, XmlHelper};
use SimpleXMLElement;

/**
 * Parser für GAEB DA XML (Feature 049, MVP-081). Ziellinie ist GAEB DA XML 3.3.
 *
 * Liest Version/Phase aus Namespace und Kopf, läuft den BoQBody rekursiv ab
 * (Kategorien → Abschnitte, Items → Positionen), baut Ordnungszahlen aus der
 * RNoPart-Kette und extrahiert Kurz-/Langtext aus den verschachtelten
 * Textcontainern. Erzeugt keine Eloquent-Objekte — Ergebnis ist ein
 * {@see ParsedBoq}. Bewusst tolerant: Strukturabweichungen werden im Preflight
 * bewertet, nicht hier hart abgewiesen.
 *
 * @phpstan-import-type ParsedSection from ParsedBoq
 * @phpstan-import-type ParsedItem from ParsedBoq
 */
class GaebDaXmlParser {
    public function parse(string $xml): ParsedBoq {
        // Härtung gegen XXE/Entity-Expansion (Feature 051): GAEB-Dateien haben
        // keine Dokumenttyp-Definition. Eine DOCTYPE wird abgewiesen, statt
        // Entities zu verarbeiten.
        if (preg_match('/<!DOCTYPE/i', $xml) === 1) {
            throw new GaebParseException('GAEB-Datei enthält eine unzulässige DOCTYPE-Deklaration.');
        }

        $version = $this->extractVersion($xml);
        $phase = $this->extractPhase($xml);

        $stripped = $this->stripNamespaces($xml);

        // Feature 052: XXE-sicheres Laden über das Common-Toolkit (LIBXML_NONET,
        // keine Entity-Substitution). DOCTYPE wurde bereits oben abgewiesen.
        $root = XmlHelper::safeLoadString($stripped);

        if ($root === false || $root->getName() !== 'GAEB') {
            throw new GaebParseException('Datei ist kein gültiges GAEB-DA-XML.');
        }

        $boq = $this->findDeep($root, 'BoQ');
        $sections = [];
        $items = [];
        $counters = ['section' => 0, 'item' => 0];

        if ($boq !== null) {
            $body = $this->findFirst($boq, 'BoQBody');
            if ($body !== null) {
                $this->walkBody($body, null, [], $sections, $items, $counters);
            }
        }

        return new ParsedBoq(
            version: $version,
            phase: $phase,
            projectName: $this->extractProjectName($root, $boq),
            externalId: $this->attr($boq, 'ID') ?? $this->attr($boq, 'DBNr'),
            sections: $sections,
            items: $items,
        );
    }

    /**
     * @param array<int, string> $ancestorParts
     * @param list<ParsedSection> $sections
     * @param list<ParsedItem> $items
     * @param array{section: int, item: int} $counters
     */
    private function walkBody(
        SimpleXMLElement $body,
        ?string $parentRef,
        array $ancestorParts,
        array &$sections,
        array &$items,
        array &$counters,
    ): void {
        foreach ($body->children() as $node) {
            $name = $node->getName();

            if ($name === 'BoQCtgy') {
                $part = $this->attr($node, 'RNoPart') ?? '';
                $parts = $ancestorParts;
                if ($part !== '') {
                    $parts[] = $part;
                }
                $ref = $this->joinRef($parts);

                $sections[] = [
                    'ref' => $ref,
                    'parent_ref' => $parentRef,
                    'label' => $this->textOf($this->findFirst($node, 'LblTx')),
                    'position' => $counters['section']++,
                ];

                $childBody = $this->findFirst($node, 'BoQBody');
                if ($childBody !== null) {
                    $this->walkBody($childBody, $ref, $parts, $sections, $items, $counters);
                }
            } elseif ($name === 'Itemlist') {
                foreach ($node->children() as $entry) {
                    if ($entry->getName() !== 'Item') {
                        continue;
                    }
                    $items[] = $this->parseItem($entry, $parentRef, $ancestorParts, $counters['item']++);
                }
            }
        }
    }

    /**
     * @param array<int, string> $ancestorParts
     * @return ParsedItem
     */
    private function parseItem(SimpleXMLElement $item, ?string $sectionRef, array $ancestorParts, int $position): array {
        $part = $this->attr($item, 'RNoPart') ?? '';
        $parts = $ancestorParts;
        if ($part !== '') {
            $parts[] = $part;
        }

        $description = $this->findFirst($item, 'Description');
        $shortText = null;
        $longText = null;
        if ($description !== null) {
            $shortText = $this->textOf($this->findDeep($description, 'OutlineText'))
                ?? $this->textOf($this->findDeep($description, 'OutlTxt'));
            $longText = $this->textOf($this->findDeep($description, 'DetailTxt'));
        }

        $qty = $this->cleanNumber($this->textOf($this->findFirst($item, 'Qty')));
        $unit = $this->trimOrNull($this->textOf($this->findFirst($item, 'QU')));
        $unitPrice = $this->cleanNumber($this->textOf($this->findFirst($item, 'UP')));
        $totalPrice = $this->cleanNumber($this->textOf($this->findFirst($item, 'IT')));

        return [
            'ref' => $this->joinRef($parts),
            'section_ref' => $sectionRef,
            'type' => $this->detectType($item, $qty, $unit, $shortText)->value,
            'short_text' => $this->trimOrNull($shortText),
            'long_text' => $this->trimOrNull($longText),
            'quantity' => $qty,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'is_addendum' => $this->isAddendum($item),
            'external_id' => $this->attr($item, 'ID'),
            'position' => $position,
        ];
    }

    private function detectType(SimpleXMLElement $item, ?string $qty, ?string $unit, ?string $shortText): BoqItemType {
        if (strtolower((string) $this->attr($item, 'LumpSumItem')) === 'yes') {
            return BoqItemType::LumpSum;
        }
        if ($this->findFirst($item, 'Provis') !== null) {
            return BoqItemType::Optional;
        }
        if (strtolower((string) $this->attr($item, 'Alternative')) === 'yes' || $this->findFirst($item, 'Alternative') !== null) {
            return BoqItemType::Alternative;
        }
        if (strtolower((string) $this->attr($item, 'MarkupItem')) === 'yes' || $this->findFirst($item, 'Markup') !== null) {
            return BoqItemType::Markup;
        }
        if (($qty === null || $unit === null) && $shortText !== null) {
            return BoqItemType::Note;
        }

        return BoqItemType::Standard;
    }

    private function isAddendum(SimpleXMLElement $item): bool {
        // GAEB kennzeichnet Nachträge über STLNo/Nachtrags-Marker; tolerant geprüft.
        return $this->findFirst($item, 'STLNo') !== null
            || strtolower((string) $this->attr($item, 'Addendum')) === 'yes';
    }

    private function extractVersion(string $xml): ?string {
        if (preg_match('#GAEB_DA_XML/DA\d+/(\d+\.\d+)#', $xml, $m) === 1) {
            return $m[1];
        }
        if (preg_match('#<VersNr>\s*([0-9.]+)\s*</VersNr>#', $xml, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractPhase(string $xml): ?string {
        if (preg_match('#GAEB_DA_XML/DA(\d+)/#', $xml, $m) === 1) {
            return $m[1];
        }
        if (preg_match('#<DP>\s*(\d+)\s*</DP>#', $xml, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractProjectName(SimpleXMLElement $root, ?SimpleXMLElement $boq): ?string {
        $prj = $this->findDeep($root, 'PrjInfo');
        $name = $this->textOf($this->findFirst($prj, 'NamePrj')) ?? $this->textOf($this->findFirst($prj, 'LblPrj'));
        if ($name !== null && trim($name) !== '') {
            return trim($name);
        }

        $boqInfo = $boq !== null ? $this->findFirst($boq, 'BoQInfo') : null;
        $name = $this->textOf($this->findFirst($boqInfo, 'Name'));

        return $name !== null && trim($name) !== '' ? trim($name) : null;
    }

    /** @param array<int, string> $parts */
    private function joinRef(array $parts): string {
        return implode('.', array_filter($parts, static fn ($p): bool => $p !== ''));
    }

    private function findFirst(?SimpleXMLElement $node, string $name): ?SimpleXMLElement {
        if ($node === null) {
            return null;
        }
        foreach ($node->children() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    /** Erste Übereinstimmung in beliebiger Tiefe (DFS). */
    private function findDeep(?SimpleXMLElement $node, string $name): ?SimpleXMLElement {
        if ($node === null) {
            return null;
        }
        foreach ($node->children() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
            $found = $this->findDeep($child, $name);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Gesamten Textinhalt eines Knotens rekursiv einsammeln (p/span-Verschachtelung). */
    private function textOf(?SimpleXMLElement $node): ?string {
        if ($node === null) {
            return null;
        }

        $parts = [];
        $own = trim((string) $node);
        if ($own !== '') {
            $parts[] = $own;
        }
        foreach ($node->children() as $child) {
            $childText = $this->textOf($child);
            if ($childText !== null && $childText !== '') {
                $parts[] = $childText;
            }
        }

        $text = trim(implode(' ', $parts));

        return $text === '' ? null : $text;
    }

    private function attr(?SimpleXMLElement $node, string $name): ?string {
        if ($node === null) {
            return null;
        }
        $value = $node[$name];

        return $value === null ? null : (string) $value;
    }

    private function trimOrNull(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** GAEB nutzt Punkt als Dezimaltrenner; tolerant gegen Komma und Tausenderzeichen. */
    private function cleanNumber(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        // NBSP vor der Normalisierung entfernen (Toolkit strippt nur ASCII-Spaces).
        $value = str_replace([' ', "\u{00A0}"], '', trim($value));
        if ($value === '') {
            return null;
        }
        $value = NumberHelper::normalizeDecimalString($value);

        return is_numeric($value) ? $value : null;
    }

    /** Default-Namespace entfernen, damit SimpleXML ohne Präfixe arbeitet. */
    private function stripNamespaces(string $xml): string {
        return (string) preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);
    }
}
