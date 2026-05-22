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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ExpenseStatus: string implements HasLabel {
    use HasOptions;

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
}
