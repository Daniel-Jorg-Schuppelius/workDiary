<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebInvoiceExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\Invoice;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebInvoice, GaebInvoiceShare, GaebParty};
use ERechnungToolkit\Enums\{GaebInvoiceShareType, GaebInvoiceType, GaebPhase};
use ERechnungToolkit\Generators\GaebDaXmlGenerator;

/**
 * Rechnungen als GAEB-Datei ausgeben (X89) bzw. als rechnungsbegründende
 * Unterlage (X89B).
 *
 * **Keine zweite Rechnungshoheit** (D8): Wo ein externes System führt — DATEV,
 * Lexoffice, orgaMAX, JTL —, bleibt es führend; diese Datei ist Ausgabeformat
 * und Anlage, kein eigener Rechnungskreis. Sie entsteht aus dem, was bereits
 * feststeht, und rechnet nichts nach.
 *
 * Den Aufbau der Rechnungsanteile gibt nicht GAEB vor, sondern der
 * Auftraggeber; der Standard liefert nur die Liste der Arten. Ohne Vorgabe
 * werden Grundbetrag und Umsatzsteuer gestellt — die beiden, die jede Rechnung
 * hat.
 */
final class GaebInvoiceExportService {
    public function __construct(
        private readonly XRechnungGenerator $einvoice,
        private readonly GaebDaXmlGenerator $generator = new GaebDaXmlGenerator,
    ) {}

    /**
     * @return array{content: string, filename: string, losses: list<string>}
     */
    public function export(Invoice $invoice, GaebPhase $phase = GaebPhase::Invoice): array {
        $currency = $invoice->currency ?? CurrencyCode::Euro;

        $content = $this->generator->generate(
            new GaebBoq(currency: $currency),
            $phase,
            $currency->value,
            $invoice->issued_on?->format('Y-m-d'),
            invoice: $this->document($invoice, $currency),
        );

        return [
            'content' => $content,
            'filename' => sprintf('Rechnung-%s.x%s', $invoice->number ?? $invoice->id, $phase->value),
            'losses' => $this->losses($invoice),
        ];
    }

    private function document(Invoice $invoice, CurrencyCode $currency): GaebInvoice {
        $seller = $this->einvoice->sellerDataFor($invoice->organization);
        $issued = $invoice->issued_on?->format('Y-m-d') ?? now()->format('Y-m-d');

        return new GaebInvoice(
            number: (string) ($invoice->number ?? $invoice->external_number ?? $invoice->id),
            date: $issued,
            type: $this->type($invoice),
            // Einen eigenen Leistungszeitraum führt die Rechnung nicht; er
            // ergibt sich aus den Positionen. Wo keiner ableitbar ist, steht
            // das Rechnungsdatum für beides - besser als ein erfundener.
            serviceStart: $this->servicePeriod($invoice)[0] ?? $issued,
            serviceEnd: $this->servicePeriod($invoice)[1] ?? $issued,
            creator: $this->party($seller['name'], $seller['street'], $seller['zip'], $seller['city']),
            creatorTaxNumber: $this->taxNumber($invoice, $seller),
            recipient: $this->recipient($invoice),
            shares: $this->shares($invoice, $currency),
            totalGross: $invoice->total,
            creditNote: $invoice->type === Invoice::TYPE_CREDIT_NOTE,
        );
    }

    /**
     * Die Rechnungsart entscheidet, was die Beträge bedeuten: Ein Abschlag wird
     * später verrechnet, eine Schlussrechnung schließt den Auftrag.
     */
    private function type(Invoice $invoice): GaebInvoiceType {
        return match ($invoice->type) {
            Invoice::TYPE_PARTIAL => GaebInvoiceType::Deduction,
            Invoice::TYPE_FINAL => GaebInvoiceType::FinalAccount,
            Invoice::TYPE_DOWN_PAYMENT => GaebInvoiceType::AdvancePayment,
            Invoice::TYPE_PROFORMA => GaebInvoiceType::ProForma,
            default => GaebInvoiceType::SingleInvoice,
        };
    }

    /**
     * Anteile in Rechenreihenfolge. Wo das Dokument eine Aufgliederung nach
     * Steuersätzen mitbringt, wird sie übernommen; sonst bleibt es bei
     * Grundbetrag und Umsatzsteuer.
     *
     * @return list<GaebInvoiceShare>
     */
    private function shares(Invoice $invoice, CurrencyCode $currency): array {
        $shares = [];

        // Die Beträge sind bereits Money-Objekte; über einen String zu gehen
        // hieße, sie zu verlieren.
        if ($invoice->subtotal !== null) {
            $shares[] = new GaebInvoiceShare(
                GaebInvoiceShareType::BasicAmount,
                (string) __('gaeb.invoice.share_net'),
                $invoice->subtotal,
            );
        }

        // Ein Nachlass senkt den Betrag - das Vorzeichen steckt in der Art,
        // nicht in der Zahl.
        $discount = $invoice->discount_amount;
        if ($discount !== null && !$discount->isZero()) {
            $shares[] = new GaebInvoiceShare(
                GaebInvoiceShareType::Discount,
                (string) __('gaeb.invoice.share_discount'),
                $discount,
            );
        }

        $breakdown = is_array($invoice->tax_breakdown) ? $invoice->tax_breakdown : [];
        if ($breakdown !== []) {
            foreach ($breakdown as $entry) {
                if (!is_array($entry) || !isset($entry['tax'])) {
                    continue;
                }
                $shares[] = new GaebInvoiceShare(
                    GaebInvoiceShareType::Vat,
                    (string) __('gaeb.invoice.share_vat', ['rate' => (string) ($entry['rate'] ?? '')]),
                    Money::of((string) $entry['tax'], $currency),
                );
            }

            return $shares;
        }

        if ($invoice->tax_amount !== null) {
            $shares[] = new GaebInvoiceShare(
                GaebInvoiceShareType::Vat,
                (string) __('gaeb.invoice.share_vat', ['rate' => (string) ($invoice->tax_rate ?? '')]),
                $invoice->tax_amount,
            );
        }

        return $shares;
    }

    /**
     * Leistungszeitraum aus den Positionen: frühestes und spätestes Datum.
     * Die Rechnung selbst führt keinen.
     *
     * @return array{?string, ?string}
     */
    private function servicePeriod(Invoice $invoice): array {
        $dates = $invoice->items
            ->map(static fn ($item) => $item->service_date)
            ->filter()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return [null, null];
        }

        /** @var \Illuminate\Support\Carbon $first */
        $first = $dates->first();
        /** @var \Illuminate\Support\Carbon $last */
        $last = $dates->last();

        return [$first->format('Y-m-d'), $last->format('Y-m-d')];
    }

    private function recipient(Invoice $invoice): ?GaebParty {
        $customer = $invoice->customer;

        return $this->party(
            $customer->name,
            $customer->address_street,
            $customer->address_zip,
            $customer->address_city,
        );
    }

    /**
     * Die Steuernummer ist Pflichtangabe des Steuerrechts, nicht des Formats.
     * Fehlt sie in den E-Rechnungs-Stammdaten, tritt die USt-IdNr an ihre
     * Stelle — beides weist den Rechnungssteller aus.
     *
     * @param array<string, mixed> $seller
     */
    private function taxNumber(Invoice $invoice, array $seller): ?string {
        foreach (['tax_number', 'vat_id'] as $key) {
            $value = trim((string) ($seller[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function party(?string $name, ?string $street, ?string $zip, ?string $city): ?GaebParty {
        if ((string) $name === '' || (string) $street === '' || (string) $zip === '' || (string) $city === '') {
            return null;
        }

        return new GaebParty((string) $name, (string) $street, (string) $zip, (string) $city);
    }

    /**
     * Was die Gegenseite beanstanden wird — die Datei entsteht trotzdem.
     *
     * @return list<string>
     */
    private function losses(Invoice $invoice): array {
        $losses = [];
        $seller = $this->einvoice->sellerDataFor($invoice->organization);

        if ($this->taxNumber($invoice, $seller) === null) {
            $losses[] = (string) __('gaeb.invoice.missing_tax_number');
        }
        if ($this->recipient($invoice) === null) {
            $losses[] = (string) __('gaeb.invoice.missing_recipient');
        }
        if ($this->servicePeriod($invoice) === [null, null]) {
            $losses[] = (string) __('gaeb.invoice.missing_service_period');
        }

        return $losses;
    }
}
