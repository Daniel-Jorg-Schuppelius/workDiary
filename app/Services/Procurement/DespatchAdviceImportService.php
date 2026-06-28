<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DespatchAdviceImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procurement;

use App\Models\{PurchaseOrder, PurchaseOrderAdvice, PurchaseOrderLine};
use ERechnungToolkit\Entities\DespatchLine;
use ERechnungToolkit\Parsers\DespatchAdviceParser;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Import eines elektronischen Lieferscheins (UBL Despatch Advice / Peppol BIS)
 * als Lieferavis ({@see PurchaseOrderAdvice}) — die eingehende Gegenrichtung zum
 * {@see PurchaseOrderExportService}.
 *
 * Der Lieferschein wird über das `php-erechnung-toolkit` geparst, die Bestellung
 * über die Bestellreferenz (`cac:OrderReference`) aufgelöst und jede
 * Lieferschein-Position über die Bestellzeilennummer
 * (`cac:OrderLineReference/cbc:LineID`, 1-basiert) bzw. ersatzweise die
 * Lieferanten-SKU der Bestellzeile zugeordnet. Das Avis wird über den
 * {@see AdviceService} angekündigt (Status „announced"); die eigentliche
 * Bestandsbuchung erfolgt unverändert über {@see AdviceService::receive()}.
 */
class DespatchAdviceImportService {
    public function __construct(private readonly AdviceService $advices) {}

    /**
     * Ob der Lieferschein-Parser des Toolkits verfügbar ist.
     */
    public function available(): bool {
        return class_exists(DespatchAdviceParser::class);
    }

    /**
     * Importiert einen Lieferschein (XML) als Lieferavis zur referenzierten
     * Bestellung.
     *
     * @throws RuntimeException Wenn der Parser fehlt, die Bestellung nicht
     *                          auffindbar ist oder keine Position zugeordnet
     *                          werden kann.
     */
    public function import(string $xml, int|string|null $createdBy = null, ?PurchaseOrder $expectedOrder = null): PurchaseOrderAdvice {
        if (! $this->available()) {
            throw new RuntimeException('Der Lieferschein-Parser des php-erechnung-toolkit ist nicht verfügbar.');
        }

        $advice = (new DespatchAdviceParser)->parse($xml);

        $orderReference = trim((string) $advice->getOrderReference());
        if ($orderReference === '') {
            throw new RuntimeException('Lieferschein ohne Bestellreferenz (cac:OrderReference).');
        }

        // Bindung an eine erwartete Bestellung (z. B. Upload auf der Bestellseite).
        if ($expectedOrder !== null && trim((string) $expectedOrder->number) !== $orderReference) {
            throw new RuntimeException("Lieferschein verweist auf eine andere Bestellung ({$orderReference}).");
        }

        // Organisations-Scope greift automatisch über BelongsToOrganization.
        $order = $expectedOrder ?? PurchaseOrder::query()->where('number', $orderReference)->first();
        if (! $order instanceof PurchaseOrder) {
            throw new RuntimeException("Keine Bestellung zur Referenz {$orderReference} gefunden.");
        }

        $order->loadMissing('lines');
        /** @var Collection<int, PurchaseOrderLine> $orderedLines */
        $orderedLines = $order->lines->sortBy('id')->values();

        $lineData = [];
        foreach ($advice->getLines() as $despatchLine) {
            $orderLine = $this->resolveLine($orderedLines, $despatchLine);
            if ($orderLine === null) {
                continue;
            }
            $lineData[] = ['line' => $orderLine, 'qty' => $this->formatQty($despatchLine->getDeliveredQuantity())];
        }

        if ($lineData === []) {
            throw new RuntimeException('Lieferschein: keine Position konnte einer Bestellzeile zugeordnet werden.');
        }

        $options = [
            'reference' => trim((string) $advice->getId()) ?: null,
            'expected_at' => $advice->getActualDeliveryDate()?->format('Y-m-d'),
            'created_by' => $createdBy,
        ];

        return $this->advices->announce($order, $lineData, $options);
    }

    /**
     * Ordnet eine Lieferschein-Position einer Bestellzeile zu: zuerst über die
     * 1-basierte Zeilennummer, ersatzweise über die Lieferanten-SKU.
     *
     * @param  Collection<int, PurchaseOrderLine>  $orderedLines
     */
    private function resolveLine(Collection $orderedLines, DespatchLine $despatchLine): ?PurchaseOrderLine {
        $lineId = trim((string) $despatchLine->getOrderLineId());
        if ($lineId !== '' && ctype_digit($lineId)) {
            $position = (int) $lineId - 1;
            $byPosition = $orderedLines->get($position);
            if ($byPosition instanceof PurchaseOrderLine) {
                return $byPosition;
            }
        }

        $sku = trim((string) $despatchLine->getSellersItemId());
        if ($sku !== '') {
            $bySku = $orderedLines->first(
                static fn (PurchaseOrderLine $line): bool => trim((string) $line->supplier_sku) === $sku,
            );
            if ($bySku instanceof PurchaseOrderLine) {
                return $bySku;
            }
        }

        return null;
    }

    private function formatQty(float $quantity): string {
        return number_format($quantity, AdviceService::SCALE, '.', '');
    }
}
