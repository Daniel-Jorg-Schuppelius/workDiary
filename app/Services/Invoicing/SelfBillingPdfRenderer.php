<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Invoicing\SelfBillingCredit;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Druckansicht einer Gutschrift im Gutschriftsverfahren (MVP-1280). Der
 * Beleg trägt die Angabe „Gutschrift“, den Lieferanten als Leistenden mit
 * Steuernummer bzw. USt-IdNr. und die eigene Organisation als Ausstellerin.
 */
class SelfBillingPdfRenderer {
    public function __construct(
        private readonly DocumentDesignRenderer $design,
        private readonly XRechnungGenerator $xrechnung,
    ) {}

    public function output(SelfBillingCredit $credit): string {
        $credit->loadMissing(['supplier', 'lines', 'organization']);
        $totals = $credit->documentTotals();

        return $this->design->renderPdf(
            RenderDocumentKind::CreditNote,
            'invoicing.self-billing.pdf',
            [
                'credit' => $credit,
                'issuer' => $this->xrechnung->sellerDataFor($credit->organization),
                'totals' => $totals,
            ],
            $credit->organization,
        );
    }

    public function filename(SelfBillingCredit $credit): string {
        return 'gutschrift-' . str_replace(['/', '\\', ' '], '-', (string) ($credit->number ?? 'entwurf-' . $credit->sqid)) . '.pdf';
    }
}
