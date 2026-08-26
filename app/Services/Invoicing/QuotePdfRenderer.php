<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuotePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Quote;
use App\Services\BrandingService;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * Rendert die Druckansicht eines Angebots (`quotes.pdf`) zu PDF-Bytes
 * (MVP-650, Issue #83): erster eigener Vertriebsbeleg-Generator neben der
 * Rechnung. Läuft über die Dokumentdesign-Pipeline; versendete Angebote
 * verwenden ihren beim Versand eingefrorenen Render-Snapshot — spätere
 * Profiländerungen verändern das versandte Dokument nicht.
 */
class QuotePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** PDF-Bytes des Angebots (A4). */
    public function output(Quote $quote): string {
        $quote->loadMissing(['items', 'customer', 'organization']);

        // Belegsprache je Kunde (Feature 034, MVP-721): nur Darstellung.
        return \App\Support\DocumentLocale::within($quote->customer, $quote->organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::Quote,
            'quotes.pdf',
            $this->viewData($quote),
            $quote->organization,
            payload: $this->designPayload($quote),
        ));
    }

    /**
     * View-Daten der Druckansicht: Rechtsangaben der Beleg-Organisation +
     * Design-Kontext (Texte/Akzentfarbe aus dem Design-Payload, MVP-651).
     *
     * @return array<string, mixed>
     */
    public function viewData(Quote $quote): array {
        $quote->loadMissing(['items', 'customer', 'organization']);

        return [
            'quote' => $quote,
            'orgLegal' => app(BrandingService::class)->legalFor($quote->organization),
            'design' => $this->design->context($this->designPayload($quote)),
            'taxRows' => $this->taxBreakdown($quote),
        ];
    }

    /**
     * Design-Payload: eingefrorener Snapshot (versendete/entschiedene
     * Angebote) vor aktivem Profil vor Systemfallback (null).
     *
     * @return array<string, mixed>|null
     */
    private function designPayload(Quote $quote): ?array {
        $snapshot = $this->design->payloadFromSnapshot($quote, RenderDocumentKind::Quote);
        if ($snapshot !== null) {
            return $snapshot;
        }

        return $quote->organization === null
            ? null
            : $this->design->payloadFor($quote->organization, RenderDocumentKind::Quote, (int) $quote->customer_id);
    }

    /**
     * Steueraufriss je Satz über die ZÄHLENDEN Positionen (Spiegel von
     * {@see Quote::recalculate()}: vor der Entscheidung Pflichtpositionen,
     * danach nur Angenommenes; Positionen ohne Satz → TaxResolver-Fallback).
     *
     * @return array<int, array{rate: float, net: float, tax: float}>
     */
    /**
     * Steueraufriss aus der einen Berechnungsstelle (Quote::taxBreakdownByRate,
     * B3) — Float nur für die View.
     *
     * @return list<array{rate: float, net: float, tax: float}>
     */
    private function taxBreakdown(Quote $quote): array {
        return array_map(static fn (array $row): array => [
            'rate' => $row['rate'],
            'net' => $row['net']->toFloat(),
            'tax' => $row['tax']->toFloat(),
        ], $quote->taxBreakdownByRate());
    }

}
