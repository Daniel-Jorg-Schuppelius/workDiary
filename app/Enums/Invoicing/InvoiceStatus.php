<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status einer Rechnung mit Übergangstabelle (Sicherheitsaudit 2026-10-04,
 * li-1/pub-4). Die Spalte `invoices.status` trägt weiter die Zeichenkette;
 * die Werte entsprechen den `Invoice::STATUS_*`-Konstanten.
 */
enum InvoiceStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /** Nimmt der Beleg in diesem Status Zahlungen an? Entwurf und Storno nie. */
    public function acceptsPayments(): bool {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Paid], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::PartiallyPaid, self::Paid, self::Cancelled],
            self::PartiallyPaid => [self::Paid, self::Issued],
            // Zurück nur, wenn die Deckung wegfällt (Zuordnung aufgehoben, Kassenstorno, Erstattung).
            self::Paid => [self::PartiallyPaid, self::Issued],
            self::Cancelled => [],
        };
    }
}
