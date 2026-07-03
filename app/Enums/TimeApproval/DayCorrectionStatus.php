<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCorrectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeApproval;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Tages-Korrekturantrags (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md §5).
 */
enum DayCorrectionStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.dayCorrection.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending  => 'info',
            self::Approved => 'success',
            self::Rejected => 'error',
        };
    }

    public function isTerminal(): bool {
        return $this !== self::Pending;
    }
}
