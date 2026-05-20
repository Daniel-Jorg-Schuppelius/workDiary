<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Task;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string {
        return (string) __('enums.task.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'neutral',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }
}
