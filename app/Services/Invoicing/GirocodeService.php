<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GirocodeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\{Invoice, Organization};
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Builders\Payment\EpcQrBuilder;
use CommonToolkit\Enums\CurrencyCode;
use Throwable;

/**
 * Girocode (EPC-QR) für Rechnungs-PDFs (Feature 111, MVP-600).
 *
 * Der Code ist ein Zahlungshilfsmittel: Die Banking-App liest ihn und füllt
 * die Überweisung vor. Der schwierige Teil ist nicht das Erzeugen, sondern
 * die Entscheidung, wann er NICHT erscheinen darf — ein QR-Code, der auf ein
 * unvollständiges Konto oder einen falschen Betrag führt, ist schlimmer als
 * gar keiner: Er sieht verbindlich aus und niemand prüft ihn nach.
 *
 * Die Nutzlast baut das common-toolkit ({@see EpcQrBuilder}, EPC069-12);
 * hier stehen nur die App-Regeln davor.
 */
class GirocodeService {
    /** Kantenlänge der SVG-Grafik in Pixeln (Druckgröße bestimmt das CSS). */
    private const SIZE_PX = 160;

    /**
     * SVG-Data-URI des Girocodes — oder null, wenn er nicht erscheinen soll.
     *
     * @param array<string, mixed> $legal Rechtsangaben der Organisation (BrandingService::legalFor)
     */
    public function dataUri(Invoice $invoice, array $legal): ?string {
        $payload = $this->payload($invoice, $legal);

        return $payload === null ? null : $this->render($payload);
    }

    /**
     * EPC-Nutzlast oder null. Öffentlich, damit Tests die Entscheidung prüfen
     * können, ohne durch den QR-Renderer zu gehen.
     *
     * @param array<string, mixed> $legal
     */
    public function payload(Invoice $invoice, array $legal): ?string {
        if (! $this->enabled($invoice)) {
            return null;
        }

        // Gutschrift/Storno: Der Kunde zahlt hier nichts — ein
        // Überweisungscode wäre schlicht falsch herum.
        if ($invoice->isCreditNote() || $invoice->isCancelled()) {
            return null;
        }

        // Der EPC-QR kennt ausschließlich EUR (EPC069-12). Eine
        // Fremdwährungsrechnung bekommt keinen Code, keinen umgerechneten.
        if ($invoice->currency !== CurrencyCode::Euro) {
            return null;
        }

        $amount = $this->amount($invoice);
        if ($amount === null) {
            return null;
        }

        $holder = trim((string) ($legal['account_holder'] ?? '')) ?: trim((string) ($invoice->organization->name ?? ''));
        $iban = trim((string) ($legal['iban'] ?? ''));
        if ($holder === '' || $iban === '') {
            return null;
        }

        try {
            $builder = EpcQrBuilder::to($holder, $iban)
                ->amount($amount)
                // Verwendungszweck ist die Rechnungsnummer, nie die interne ID:
                // Sie steht auf dem Beleg und der Zahlungsabgleich sucht danach.
                ->remittance((string) $invoice->number);

            $bic = trim((string) ($legal['bic'] ?? ''));
            if ($bic !== '') {
                $builder->bic($bic);
            }

            return $builder->build();
        } catch (Throwable) {
            // Ungültige IBAN/BIC, zu langer Firmenname (331-Byte-Grenze):
            // Der Textblock allein bleibt stehen. Ein Fehler beim Girocode
            // darf nie die Rechnung verhindern.
            return null;
        }
    }

    /**
     * Ist der Girocode für diese Organisation eingeschaltet?
     *
     * Bewusst aus der Organisation DER RECHNUNG statt über den Ambient-Kontext
     * ({@see Setting::get()}): Das PDF entsteht auch im Queue-Worker beim
     * Mailversand, und dort gibt es keine aktive Organisation — dieselbe
     * Begründung wie bei den Rechtsangaben in {@see InvoicePdfRenderer::viewData()}.
     */
    public function enabled(Invoice $invoice): bool {
        $organization = $invoice->organization;
        if ($organization instanceof Organization) {
            $value = data_get((array) ($organization->settings ?? []), 'invoicing.girocode_enabled');
            if ($value !== null) {
                return (bool) $value;
            }
        }

        return (bool) config('invoicing.girocode_enabled', false);
    }

    /**
     * Zu zahlender Betrag.
     *
     * Eine bezahlte Rechnung bekommt keinen Code. Bei `partially_paid` fehlt
     * lokal die Restsumme — workDiary führt keine Teilzahlungsbeträge, nur
     * den Status — und ein Code über den VOLLEN Betrag würde zu einer
     * Doppelzahlung einladen. Deshalb: auch dann kein Code, der Textblock
     * nennt weiterhin die Rechnungssumme.
     */
    private function amount(Invoice $invoice): ?float {
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true)) {
            return null;
        }

        $total = $invoice->total?->toFloat() ?? 0.0;

        return $total >= 0.01 ? round($total, 2) : null;
    }

    private function render(string $payload): string {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(self::SIZE_PX, 1), new SvgImageBackEnd())))
            ->writeString($payload);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
