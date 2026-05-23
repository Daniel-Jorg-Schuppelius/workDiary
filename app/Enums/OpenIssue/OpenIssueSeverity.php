<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\OpenIssue;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum OpenIssueSeverity: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.open-issue.severity.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'ghost',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'error',
        };
    }

    public function requiresAssignee(): bool {
        return in_array($this, [self::High, self::Critical], true);
    }

    public function requiresDueDate(): bool {
        return $this === self::Critical;
    }
}
