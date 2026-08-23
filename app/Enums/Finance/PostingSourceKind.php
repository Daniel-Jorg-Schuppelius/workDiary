<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingSourceKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Quellenart eines Buchungsvorschlags (Feature 125, MVP-673).
 *
 * Jede Art hat genau einen Adapter. Die Aufzählung ist der Vertrag zwischen
 * Inbox, Mappingregeln und Adaptern — neue Quellen entstehen hier, nicht als
 * Sonderfall in einer Regel.
 */
enum PostingSourceKind: string implements HasLabel {
    use HasOptions;

    case SalesInvoice = 'sales_invoice';
    case IncomingInvoice = 'incoming_invoice';
    case Expense = 'expense';
    case CashEntry = 'cash_entry';
    case Payment = 'payment';

    public function label(): string {
        return (string) __('enums.finance.posting-source-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::SalesInvoice => 'success',
            self::IncomingInvoice => 'info',
            self::Expense => 'warning',
            self::CashEntry => 'secondary',
            self::Payment => 'primary',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::SalesInvoice => 'request_quote',
            self::IncomingInvoice => 'inbox',
            self::Expense => 'receipt',
            self::CashEntry => 'payments',
            self::Payment => 'account_balance',
        };
    }

    /** Präfix des Idempotenzschlüssels (`invoice:42`). */
    public function keyPrefix(): string {
        return match ($this) {
            self::SalesInvoice => 'invoice',
            self::IncomingInvoice => 'incoming',
            self::Expense => 'expense',
            self::CashEntry => 'cash',
            self::Payment => 'payment',
        };
    }
}
