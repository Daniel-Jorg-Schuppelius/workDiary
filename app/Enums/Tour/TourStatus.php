<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Tour;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TourStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return (string) __('enums.tour.status.'.$this->value);
    }
}
