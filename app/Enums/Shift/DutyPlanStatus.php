<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Shift;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum DutyPlanStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string {
        return (string) __('duty_plan.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Published => 'info',
        };
    }
}
