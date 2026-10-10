<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stand der Übergabe eines Rechnungseingangs an ein Buchhaltungsziel
 * (Feature 163, MVP-1111). Übergeben und verknüpft sind endgültig: ein
 * angelegter Beleg ist im Zielsystem per API nicht mehr zu löschen.
 */
enum IncomingInvoiceTransferStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    /** Wartet auf etwas, das ein Mensch klären muss (Kontakt, Werte, abweichender Betrag). */
    case Waiting = 'waiting';
    case Failed = 'failed';
    case Transferred = 'transferred';
    /** Lag im Zielsystem schon vor und wurde nur verknüpft. */
    case Linked = 'linked';

    public function label(): string {
        return match ($this) {
            self::Pending => (string) __('enums.invoicing.incoming_invoice_transfer_status.pending'),
            self::Waiting => (string) __('enums.invoicing.incoming_invoice_transfer_status.waiting'),
            self::Failed => (string) __('enums.invoicing.incoming_invoice_transfer_status.failed'),
            self::Transferred => (string) __('enums.invoicing.incoming_invoice_transfer_status.transferred'),
            self::Linked => (string) __('enums.invoicing.incoming_invoice_transfer_status.linked'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'ghost',
            self::Waiting => 'warning',
            self::Failed => 'error',
            self::Transferred, self::Linked => 'success',
        };
    }

    public function isFinal(): bool {
        return $this === self::Transferred || $this === self::Linked;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending, self::Waiting, self::Failed => [self::Pending, self::Waiting, self::Failed, self::Transferred, self::Linked],
            self::Transferred, self::Linked => [],
        };
    }
}
