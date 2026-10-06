<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRequisitionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand eines Stellenbedarfs (Feature 068, MVP-189); frei setzbar, ohne festen Ablauf. */
enum JobRequisitionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'primary',
            self::OnHold => 'warning',
            self::Filled => 'success',
            self::Closed => 'neutral',
            self::Draft => 'ghost',
        };
    }
}
