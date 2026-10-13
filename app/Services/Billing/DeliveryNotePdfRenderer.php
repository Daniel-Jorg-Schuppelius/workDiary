<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNotePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Billing\DeliveryNote;
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Support\DocumentLocale;

/**
 * Lieferschein-PDF (Feature 047, MVP-074; Beleg seit Phase 145, MVP-1163).
 * Reiner Übergabenachweis ohne Preise — die Rechnungshoheit liegt je nach
 * Kunde beim Fakturasystem.
 */
class DeliveryNotePdfRenderer {
    public function render(DeliveryNote $note): string {
        $note->loadMissing(['customer', 'items.stockDelivery.warehouse', 'manufacturingOrder', 'orderConfirmation']);
        $organization = Organization::query()->withoutGlobalScopes()->find($note->organization_id);

        // MVP-650: ausgelieferte Lieferscheine rendern mit ihrem eingefrorenen
        // Designstand; vor Phase 145 lag er an der Lagerauslieferung.
        $design = app(DocumentDesignRenderer::class);
        $payload = $design->payloadFromSnapshot($note, RenderDocumentKind::DeliveryNote);
        $legacy = $note->items->first()?->stockDelivery;
        if ($payload === null && $legacy !== null) {
            $payload = $design->payloadFromSnapshot($legacy, RenderDocumentKind::DeliveryNote);
        }

        // Belegsprache je Kunde (Feature 034, MVP-721): nur Darstellung.
        return DocumentLocale::within($note->customer, $organization, fn (): string => $design->renderPdf(
            RenderDocumentKind::DeliveryNote,
            'pdf.delivery-note',
            [
                'note' => $note,
                'organization' => $organization,
                'number' => $note->number,
                'warehouses' => $note->items->map(fn ($item) => $item->stockDelivery?->warehouse?->name)->filter()->unique()->values(),
            ],
            $organization,
            payload: $payload,
        ));
    }

    public function filename(DeliveryNote $note): string {
        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $note->number) . '.pdf';
    }
}
