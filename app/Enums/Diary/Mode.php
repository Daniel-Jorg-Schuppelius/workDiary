<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Mode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum Mode: string implements HasLabel
{
    use HasOptions;

    case Fixed = 'fixed';
    case Deadline = 'deadline';
    case Window = 'window';
    case Recurring = 'recurring';
    case Backlog = 'backlog';

    public function label(): string
    {
        return (string) __('diary.mode.'.$this->value);
    }
}
