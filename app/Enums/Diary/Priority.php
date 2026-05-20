<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Priority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Diary;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum Priority: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string {
        return (string) __('diary.priority.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'ghost',
            self::Normal => 'info',
            self::High => 'warning',
            self::Urgent => 'error',
        };
    }
}
