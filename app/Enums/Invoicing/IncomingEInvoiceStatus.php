<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Prüfstand einer eingehenden E-Rechnung (Feature 066, MVP-167): fachliche Freigabe, erst danach die Zahlungsfreigabe. */
enum IncomingEInvoiceStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Received = 'received';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Question = 'question';
    case PaymentReleased = 'payment_released';

    public function label(): string {
        return match ($this) {
            self::Received => (string) __('enums.invoicing.incoming_e_invoice_status.received'),
            self::Approved => (string) __('enums.invoicing.incoming_e_invoice_status.approved'),
            self::Rejected => (string) __('enums.invoicing.incoming_e_invoice_status.rejected'),
            self::Question => (string) __('enums.invoicing.incoming_e_invoice_status.question'),
            self::PaymentReleased => (string) __('enums.invoicing.incoming_e_invoice_status.payment_released'),
        };
    }

    /**
     * Eine Rückfrage lässt sich wiederholen; das prüft der Aufrufer selbst.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Received => [self::Approved, self::Rejected, self::Question],
            self::Question => [self::Approved, self::Rejected],
            self::Approved => [self::PaymentReleased, self::Rejected],
            self::Rejected, self::PaymentReleased => [],
        };
    }
}
