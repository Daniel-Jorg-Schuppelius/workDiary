<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceDeliveryFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum InvoiceDeliveryFormat: string implements HasLabel {
    use HasOptions;

    case Pdf = 'pdf';
    case XRechnung = 'xrechnung';
    case Zugferd = 'zugferd';
    case PdfAndXRechnung = 'pdf_xrechnung';
    /** XRechnung in UN/CEFACT-CII-Syntax (MVP-805) — nur, wenn der Empfänger sie verlangt; UBL bleibt Standard. */
    case XRechnungCii = 'xrechnung_cii';

    public function label(): string {
        return match ($this) {
            self::Pdf => (string) __('invoice-import.format.pdf'),
            self::XRechnung => (string) __('invoice-import.format.xrechnung'),
            self::Zugferd => (string) __('invoice-import.format.zugferd'),
            self::PdfAndXRechnung => (string) __('invoice-import.format.pdf_xrechnung'),
            self::XRechnungCii => (string) __('invoice-import.format.xrechnung_cii'),
        };
    }

    public function isElectronic(): bool {
        return $this !== self::Pdf;
    }

    public function needsXRechnung(): bool {
        return in_array($this, [self::XRechnung, self::PdfAndXRechnung, self::XRechnungCii], true);
    }

    /** Syntax der XRechnung: CII nur auf ausdrücklichen Wunsch, sonst UBL. */
    public function xrechnungSyntax(): XRechnungSyntax {
        return $this === self::XRechnungCii ? XRechnungSyntax::Cii : XRechnungSyntax::Ubl;
    }

    public function needsZugferd(): bool {
        return $this === self::Zugferd;
    }

    public function dispatchFormat(): string {
        return match ($this) {
            self::Pdf => 'pdf',
            self::XRechnung => 'xrechnung_ubl',
            self::Zugferd => 'zugferd_pdf',
            self::PdfAndXRechnung => 'pdf+xrechnung_ubl',
            self::XRechnungCii => 'xrechnung_cii',
        };
    }
}
