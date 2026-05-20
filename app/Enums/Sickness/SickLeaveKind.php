<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeaveKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Sickness;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum SickLeaveKind: string implements HasLabel {
    use HasOptions;

    case Initial = 'initial';
    case FollowUp = 'follow_up';

    public function label(): string {
        return (string) __('enums.sickness.kind.' . $this->value);
    }
}
