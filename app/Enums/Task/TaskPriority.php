<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskPriority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Task;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TaskPriority: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string {
        return (string) __('enums.task.priority.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'ghost',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'error',
        };
    }

    public function color(): string {
        return match ($this) {
            self::Low => '#94a3b8',
            self::Medium => '#3b82f6',
            self::High => '#f59e0b',
            self::Urgent => '#ef4444',
        };
    }
}
