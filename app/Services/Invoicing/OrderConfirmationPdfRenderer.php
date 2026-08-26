<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Quote;
use App\Services\BrandingService;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use RuntimeException;

/**
 * Auftragsbestätigung zu einem angenommenen Angebot (MVP-650, Issue #83):
 * bestätigt genau die ANGENOMMENEN Positionen (Voll- oder Teilannahme).
 * Datengrundlage ist das Angebot nach der Entscheidung — die Positionen sind
 * ab da unveränderlich (Item-CRUD nur im Entwurf), die Summen wurden bei der
 * Annahme neu berechnet. Kein eigener Nummernkreis: die AB referenziert das
 * Angebot (Folgeschnitt: eigene AB-Nummern, wenn ein Auftragsobjekt entsteht).
 * Das Design erbt über die Fallback-Art das Rechnungs- bzw. CI-Basisprofil;
 * bei der Annahme wird der Stand als Render-Snapshot eingefroren.
 */
class OrderConfirmationPdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** PDF-Bytes der Auftragsbestätigung (A4). */
    public function output(Quote $quote): string {
        if (! in_array($quote->status, ['accepted', 'partially_accepted'], true)) {
            throw new RuntimeException((string) __('Nur angenommene Angebote können bestätigt werden.'));
        }
        $quote->loadMissing(['items', 'customer', 'organization']);

        // Belegsprache je Kunde (Feature 034, MVP-721): nur Darstellung.
        return \App\Support\DocumentLocale::within($quote->customer, $quote->organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::OrderConfirmation,
            'quotes.order-confirmation-pdf',
            $this->viewData($quote),
            $quote->organization,
            payload: $this->designPayload($quote),
        ));
    }

    /** @return array<string, mixed> */
    public function viewData(Quote $quote): array {
        $quote->loadMissing(['items', 'customer', 'organization']);

        return [
            'quote' => $quote,
            'acceptedItems' => $quote->items->filter(fn ($item): bool => (bool) $item->accepted)->values(),
            'orgLegal' => app(BrandingService::class)->legalFor($quote->organization),
            'design' => $this->design->context($this->designPayload($quote)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function designPayload(Quote $quote): ?array {
        $snapshot = $this->design->payloadFromSnapshot($quote, RenderDocumentKind::OrderConfirmation);
        if ($snapshot !== null) {
            return $snapshot;
        }

        return $quote->organization === null
            ? null
            : $this->design->payloadFor($quote->organization, RenderDocumentKind::OrderConfirmation, (int) $quote->customer_id);
    }
}
