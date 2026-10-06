<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Expense;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

enum ExpenseStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reimbursed = 'reimbursed';
    case Invoiced = 'invoiced';

    public function label(): string {
        return (string) __('enums.expense.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'error',
            self::Cancelled => 'ghost',
            self::Reimbursed => 'success',
            self::Invoiced => 'success',
        };
    }

    /** Endzustände, in denen Bearbeitung/Stornierung nicht mehr möglich ist. */
    public function isFinal(): bool {
        return in_array($this, [
            self::Rejected,
            self::Cancelled,
            self::Reimbursed,
            self::Invoiced,
        ], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Reimbursed, self::Invoiced, self::Cancelled],
            // Abgelehnt lässt sich nach Korrektur neu einreichen.
            self::Rejected => [self::Pending],
            // Die Rechnungsposition wird wieder freigegeben.
            self::Invoiced => [self::Approved],
            self::Reimbursed, self::Cancelled => [],
        };
    }
}
