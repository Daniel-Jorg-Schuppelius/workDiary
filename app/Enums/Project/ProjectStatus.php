<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Project;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.project.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Archived => 'ghost',
        };
    }
}
