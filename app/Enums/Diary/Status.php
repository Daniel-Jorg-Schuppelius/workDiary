<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Status.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum Status: int implements HasLabel {
    use HasOptions;

    case Done = -1;
    case InProgress = 1;
    case Open = 2;
    case Problem = 3;

    public function label(): string {
        return (string) __('diary.status.' . $this->name);
    }

    public function tone(): string {
        return match ($this) {
            self::Done => 'done',
            self::InProgress => 'progress',
            self::Open => 'open',
            self::Problem => 'alert',
        };
    }
}
