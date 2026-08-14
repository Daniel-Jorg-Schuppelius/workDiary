<?php

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

    public function label(): string {
        return match ($this) {
            self::Pdf => (string) __('invoice-import.format.pdf'),
            self::XRechnung => (string) __('invoice-import.format.xrechnung'),
            self::Zugferd => (string) __('invoice-import.format.zugferd'),
            self::PdfAndXRechnung => (string) __('invoice-import.format.pdf_xrechnung'),
        };
    }

    public function isElectronic(): bool {
        return $this !== self::Pdf;
    }

    public function needsXRechnung(): bool {
        return in_array($this, [self::XRechnung, self::PdfAndXRechnung], true);
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
        };
    }
}
