<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Format des Originals einer extern ausgestellten Rechnung und damit der
 * Versandfassung (Feature 196): PDF mit Beilagen, XRechnung mit BG-24 oder
 * ZUGFeRD mit ergänzter XML und Supplement-Dateien.
 */
enum ExternalInvoiceFormat: string implements HasLabel {
    use HasOptions;

    case Pdf = 'pdf';
    case XRechnungUbl = 'xrechnung_ubl';
    case XRechnungCii = 'xrechnung_cii';
    case Zugferd = 'zugferd';

    public function label(): string {
        return (string) __('enums.finance.external-invoice-format.' . $this->value);
    }

    public function isElectronic(): bool {
        return $this !== self::Pdf;
    }

    public function isXml(): bool {
        return $this === self::XRechnungUbl || $this === self::XRechnungCii;
    }

    /** Peppol BIS Billing 3.0 ist UBL. */
    public function peppolCapable(): bool {
        return $this === self::XRechnungUbl;
    }

    public function extension(): string {
        return $this->isXml() ? 'xml' : 'pdf';
    }

    public function mime(): string {
        return $this->isXml() ? 'application/xml' : 'application/pdf';
    }
}
