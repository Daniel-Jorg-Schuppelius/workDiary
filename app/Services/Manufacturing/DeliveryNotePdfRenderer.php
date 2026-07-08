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

namespace App\Services\Manufacturing;

use App\Models\{Organization, StockDelivery};
use Illuminate\Support\Facades\View;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Rendert eine Auslieferung als Lieferschein-PDF (Feature 047, MVP-074).
 * Reiner Übergabenachweis (kein Faktura-Beleg) — die Rechnungshoheit liegt je
 * nach Kunde beim externen Fakturasystem.
 */
class DeliveryNotePdfRenderer {
    public function render(StockDelivery $delivery): string {
        $delivery->loadMissing(['customer', 'variant.article', 'order', 'warehouse']);
        $organization = Organization::query()->withoutGlobalScopes()->find($delivery->organization_id);

        $html = View::make('pdf.delivery-note', [
            'delivery' => $delivery,
            'organization' => $organization,
            'number' => $this->number($delivery),
        ])->render();

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (pdf.delivery-note).');
    }

    /** Lieferschein-Nummer (stabil aus der Auslieferungs-ID abgeleitet). */
    public function number(StockDelivery $delivery): string {
        return 'LS-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT);
    }
}
