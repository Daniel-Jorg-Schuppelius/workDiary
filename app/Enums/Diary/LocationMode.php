<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum LocationMode: string implements HasLabel {
    use HasOptions;

    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    public function label(): string {
        return (string) __('diary.location_mode.' . $this->value);
    }
}
