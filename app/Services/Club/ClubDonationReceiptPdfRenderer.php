<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationReceiptPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Club\ClubDonationReceipt;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Zuwendungsbestätigung als PDF (MVP-1003) nach amtlichem Muster — stets auf
 * Deutsch, weil nur das amtliche Muster anerkannt wird; Angaben aus dem
 * eingefrorenen Stand der Bestätigung.
 */
class ClubDonationReceiptPdfRenderer {
    public function __construct(
        private readonly DocumentDesignRenderer $design,
        private readonly ClubDonationService $donations,
    ) {}

    public function output(ClubDonationReceipt $receipt): string {
        $receipt->loadMissing(['donations', 'organization']);
        $organization = $receipt->organization;
        $seller = app(XRechnungGenerator::class)->sellerDataFor($organization);

        return $this->design->renderPdf(RenderDocumentKind::Certificate, 'club.pdf.donation_receipt', [
            'receipt' => $receipt,
            'issuer' => [
                'name' => $seller['name'],
                'lines' => array_values(array_filter([$seller['street'], trim($seller['zip'] . ' ' . $seller['city'])])),
            ],
            'amountInWords' => $this->donations->amountInWords($receipt->total_amount),
        ], $organization);
    }

    public function filename(ClubDonationReceipt $receipt): string {
        return 'zuwendungsbestaetigung-' . $receipt->year . '-' . $receipt->receipt_no . '.pdf';
    }
}
