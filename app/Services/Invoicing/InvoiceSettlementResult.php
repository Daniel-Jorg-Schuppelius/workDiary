<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSettlementResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;

/** Ergebnis von {@see InvoiceSettlement::sync()}: Status vorher und nachher. */
final readonly class InvoiceSettlementResult {
    public function __construct(
        public ?InvoiceStatus $from,
        public ?InvoiceStatus $to,
        /** false: der Beleg nimmt keine Zahlungen an (Entwurf, Storno, Gutschrift, Pro-forma). */
        public bool $settleable,
    ) {}

    public function changed(): bool {
        return $this->from !== $this->to;
    }

    /** Deckung weggefallen: von „bezahlt“ oder „teilbezahlt“ zurück. */
    public function lowered(): bool {
        return $this->changed() && in_array($this->to, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)
            && ($this->from === InvoiceStatus::Paid || $this->to === InvoiceStatus::Issued);
    }

    public function isPaid(): bool {
        return $this->to === InvoiceStatus::Paid;
    }
}
