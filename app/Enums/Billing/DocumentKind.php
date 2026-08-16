<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vorgangsart eines Belegs im Belegfluss (Feature 105, MVP-542).
 *
 * Orthogonal zur {@see DocumentDirection}: dieselbe Art tritt in beiden
 * Richtungen auf (Rechnung/Eingangsrechnung, Gutschrift/Eingangsgutschrift).
 */
enum DocumentKind: string implements HasLabel {
    use HasOptions;

    case Quote = 'quote';
    case OrderConfirmation = 'order_confirmation';
    case DeliveryNote = 'delivery_note';
    case Invoice = 'invoice';
    case DownPayment = 'down_payment';
    case DownPaymentDeduction = 'down_payment_deduction';
    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';
    case Expense = 'expense';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.billing.kind.' . $this->value);
    }

    /**
     * Vorzeichen für Geldsummen. Gutschriften, Stornos und
     * Abschlagsverrechnungen mindern, alles andere addiert.
     */
    public function sign(): int {
        return match ($this) {
            self::CreditNote, self::Cancellation, self::DownPaymentDeduction => -1,
            self::Quote, self::OrderConfirmation, self::DeliveryNote => 0,
            default => 1,
        };
    }

    /** Rechnungsartige Vorgänge — Grundlage des Tabs „Rechnungen". */
    public function isInvoiceLike(): bool {
        return in_array($this, [self::Invoice, self::DownPayment, self::DownPaymentDeduction], true);
    }

    /** Wertmindernde Vorgänge — Grundlage des Tabs „Gutschriften". */
    public function isCreditLike(): bool {
        return in_array($this, [self::CreditNote, self::Cancellation], true);
    }
}
