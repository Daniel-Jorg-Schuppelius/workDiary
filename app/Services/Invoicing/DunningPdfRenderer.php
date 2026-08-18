<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Invoice;
use App\Services\BrandingService;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Carbon\CarbonInterface;

/**
 * Mahnschreiben zu einer überfälligen Rechnung als eigenes PDF (MVP-650,
 * Issue #83): Stufe 1 = Zahlungserinnerung, Stufen 2–3 = Mahnung. Das
 * Schreiben listet die offene Forderung (Beleg, Belegdatum, Fälligkeit,
 * offener Betrag), optional Mahngebühr und Zahlungsziel — es bleibt ein
 * Anschreiben, KEIN neuer Beleg (kein Nummernkreis, „Mahnstatus ist
 * Lifecycle"). Kein Render-Snapshot: gerendert wird beim Versand, das
 * versandte PDF liegt als Mail-Anhang vor.
 */
class DunningPdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** PDF-Bytes des Mahnschreibens (A4). */
    public function output(Invoice $invoice, int $level, ?string $note = null, ?float $fee = null, ?CarbonInterface $payUntil = null): string {
        $invoice->loadMissing(['customer', 'organization']);

        return $this->design->renderPdf(
            RenderDocumentKind::Dunning,
            'invoices.dunning-pdf',
            $this->viewData($invoice, $level, $note, $fee, $payUntil),
            $invoice->organization,
            payload: $this->designPayload($invoice),
        );
    }

    /** @return array<string, mixed> */
    public function viewData(Invoice $invoice, int $level, ?string $note = null, ?float $fee = null, ?CarbonInterface $payUntil = null): array {
        $invoice->loadMissing(['customer', 'organization']);
        $organization = $invoice->organization;

        return [
            'invoice' => $invoice,
            'level' => $level,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'fee' => $fee !== null && $fee > 0 ? round($fee, 2) : null,
            'payUntil' => $payUntil,
            'orgLegal' => app(BrandingService::class)->legalFor($organization),
            'design' => $this->design->context($this->designPayload($invoice)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function designPayload(Invoice $invoice): ?array {
        return $invoice->organization === null
            ? null
            : $this->design->payloadFor($invoice->organization, RenderDocumentKind::Dunning, (int) $invoice->customer_id);
    }
}
