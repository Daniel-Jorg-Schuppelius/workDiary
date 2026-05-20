<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceFrequency.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Recurrence;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum RecurrenceFrequency: string implements HasLabel {
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string {
        return (string) __('recurrence.frequency.' . $this->value);
    }
}
