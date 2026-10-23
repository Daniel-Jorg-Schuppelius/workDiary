<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebOrderResponseReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Services\Procurement\Contracts\PurchaseOrderGaebResponseReader;
use App\Services\Procurement\Dto\{OrderResponseData, OrderResponseLine};
use ERechnungToolkit\Entities\Gaeb\GaebOrderItem;
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Parsers\GaebReader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Rücklauf des Händlers auf dem GAEB-Handelsweg (MVP-1300): X97 bestätigt
 * die Bestellung X96 (Bestellnummer in `PartNo`), X94 beantwortet die
 * Preisanfrage X93 (Anfragenummer in `InquiryNo`). Zeilen tragen keine
 * Nummer; zugeordnet wird über die Artikelnummer des Lieferanten.
 */
final class GaebOrderResponseReader implements PurchaseOrderGaebResponseReader {
    public function __construct(private readonly GaebReader $reader = new GaebReader) {}

    public function read(string $content): ?OrderResponseData {
        try {
            $order = $this->reader->readOrder($content);
        } catch (InvalidArgumentException) {
            return null;
        }

        [$kind, $number, $ref] = match ($order->getPhase()) {
            GaebPhase::OrderConfirmation => [OrderResponseData::CONFIRMATION, $order->getOrderNo(), $order->getOrderConfirmationNo()],
            GaebPhase::PriceOffer => [OrderResponseData::OFFER, $order->getInquiryNo(), $order->getOfferNo()],
            default => throw new RuntimeException((string) __('procurement.confirmation.error.gaeb_phase', ['phase' => 'X' . ($order->getPhase()->value ?? '?')])),
        };

        $delivery = $order->getDeliveryDate() !== '' ? $order->getDeliveryDate() : null;

        return new OrderResponseData(
            kind: $kind,
            orderNumber: (string) $number,
            supplierRef: $ref,
            lines: array_map(static fn (GaebOrderItem $item): OrderResponseLine => new OrderResponseLine(
                lineId: null,
                sellerItemId: $item->getSupplierArticleNo() ?? $item->getCatalogArticleNo(),
                quantity: is_numeric($item->getQuantity()) ? (float) $item->getQuantity() : null,
                unitPrice: $item->getPrice()?->getAmount(),
                deliveryDate: $item->getDeliveryDate() ?? $delivery,
            ), $order->getItems()),
        );
    }
}
