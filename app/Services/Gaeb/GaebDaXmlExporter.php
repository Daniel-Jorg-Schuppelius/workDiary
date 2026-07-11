<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebDaXmlExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\GaebPhase;
use App\Models\{BillOfQuantity, BoqItem, BoqSection};
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;

/**
 * Generator für GAEB DA XML (Feature 049, MVP-085). Erzeugt aus einem LV einen
 * GAEB-Stand der gewünschten Austauschphase (Ziellinie 3.3). Deterministisch
 * (kein Zeitstempel im Rumpf außer dem Kopf-Datum) — gleiche Daten ergeben
 * denselben Inhalt. Gegenstück zu {@see GaebDaXmlParser}.
 */
class GaebDaXmlExporter {
    private const VERSION = '3.3';

    public function export(BillOfQuantity $boq, GaebPhase $phase, ?string $date = null): string {
        $boq->loadMissing(['sections', 'items']);

        $ns = sprintf('http://www.gaeb.de/GAEB_DA_XML/DA%s/%s', $phase->value, self::VERSION);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS($ns, 'GAEB');
        $dom->appendChild($root);

        $info = $dom->createElement('GAEBInfo');
        $info->appendChild($dom->createElement('Version', '3'));
        $info->appendChild($dom->createElement('VersDate', '2024-01-01'));
        $info->appendChild($dom->createElement('Date', $date ?? '2026-01-01'));
        $info->appendChild($dom->createElement('ProgSystem', 'WorkDiary'));
        $root->appendChild($info);

        $prj = $dom->createElement('PrjInfo');
        $prj->appendChild($this->textElement($dom, 'NamePrj', $boq->name));
        $prj->appendChild($dom->createElement('Cur', $boq->currency->value));
        $root->appendChild($prj);

        $award = $dom->createElement('Award');
        $award->appendChild($dom->createElement('DP', $phase->value));
        $award->appendChild($dom->createElement('Cur', $boq->currency->value));

        $boqEl = $dom->createElement('BoQ');
        if ($boq->external_id !== null) {
            $boqEl->setAttribute('ID', $boq->external_id);
        }
        $boqInfo = $dom->createElement('BoQInfo');
        $boqInfo->appendChild($this->textElement($dom, 'Name', $boq->name));
        $boqEl->appendChild($boqInfo);

        $body = $dom->createElement('BoQBody');
        $this->appendBody($dom, $body, $boq, $phase, null, '');
        $boqEl->appendChild($body);

        $award->appendChild($boqEl);
        $root->appendChild($award);

        return (string) $dom->saveXML();
    }

    /** Rekursiv: Abschnitte unter $parentId, dann Positionen dieses Knotens. */
    private function appendBody(DOMDocument $dom, DOMElement $body, BillOfQuantity $boq, GaebPhase $phase, ?int $parentId, string $parentRef): void {
        foreach ($this->sectionsByParent($boq, $parentId) as $section) {
            $ctgy = $dom->createElement('BoQCtgy');
            $ctgy->setAttribute('RNoPart', $this->localPart($section->reference_no, $parentRef));
            if ($section->label !== null) {
                $ctgy->appendChild($this->htmlText($dom, 'LblTx', $section->label));
            }
            $childBody = $dom->createElement('BoQBody');
            $this->appendBody($dom, $childBody, $boq, $phase, $section->id, $section->reference_no);
            $ctgy->appendChild($childBody);
            $body->appendChild($ctgy);
        }

        $items = $this->itemsBySection($boq, $parentId);
        if ($items->isNotEmpty()) {
            $list = $dom->createElement('Itemlist');
            foreach ($items as $item) {
                $list->appendChild($this->itemElement($dom, $item, $phase, $parentRef));
            }
            $body->appendChild($list);
        }
    }

    private function itemElement(DOMDocument $dom, BoqItem $item, GaebPhase $phase, string $parentRef): DOMElement {
        $el = $dom->createElement('Item');
        $el->setAttribute('RNoPart', $this->localPart($item->reference_no, $parentRef));

        if ($item->quantity !== null) {
            $el->appendChild($dom->createElement('Qty', $this->num($item->quantity)));
        }
        if ($item->unit !== null) {
            $el->appendChild($this->textElement($dom, 'QU', $item->unit));
        }

        $desc = $dom->createElement('Description');
        $complete = $dom->createElement('CompleteText');
        if ($item->short_text !== null) {
            $outline = $dom->createElement('OutlineText');
            $outlTxt = $dom->createElement('OutlTxt');
            $outlTxt->appendChild($this->htmlText($dom, 'TextOutlTxt', $item->short_text));
            $outline->appendChild($outlTxt);
            $complete->appendChild($outline);
        }
        if ($item->long_text !== null) {
            $detail = $dom->createElement('DetailTxt');
            $detail->appendChild($this->htmlText($dom, 'Text', $item->long_text));
            $complete->appendChild($detail);
        }
        $desc->appendChild($complete);
        $el->appendChild($desc);

        if ($phase->carriesPrices() && $item->unit_price !== null) {
            $el->appendChild($dom->createElement('UP', $this->num($item->unit_price)));
            if ($item->total_price !== null) {
                $el->appendChild($dom->createElement('IT', $this->num($item->total_price)));
            }
        }

        return $el;
    }

    /** @return Collection<int, BoqSection> */
    private function sectionsByParent(BillOfQuantity $boq, ?int $parentId): Collection {
        return $boq->sections
            ->where('parent_id', $parentId)
            ->sortBy('position')
            ->values();
    }

    /** @return Collection<int, BoqItem> */
    private function itemsBySection(BillOfQuantity $boq, ?int $sectionId): Collection {
        return $boq->items
            ->where('boq_section_id', $sectionId)
            ->sortBy('position')
            ->values();
    }

    /** Lokaler RNoPart = Ordnungszahl ohne den Präfix des Elternknotens. */
    private function localPart(string $reference, string $parentRef): string {
        if ($parentRef !== '' && str_starts_with($reference, $parentRef . '.')) {
            return substr($reference, strlen($parentRef) + 1);
        }

        return $reference;
    }

    private function num(string $value): string {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function textElement(DOMDocument $dom, string $name, string $value): DOMElement {
        return $dom->createElement($name, htmlspecialchars($value, ENT_XML1));
    }

    /** GAEB-Textcontainer mit p/span-Struktur. */
    private function htmlText(DOMDocument $dom, string $name, string $value): DOMElement {
        $wrapper = $dom->createElement($name);
        $p = $dom->createElement('p');
        $span = $dom->createElement('span', htmlspecialchars($value, ENT_XML1));
        $p->appendChild($span);
        $wrapper->appendChild($p);

        return $wrapper;
    }
}
