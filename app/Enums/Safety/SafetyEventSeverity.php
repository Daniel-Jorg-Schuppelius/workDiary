<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Schweregrad eines Sicherheitsereignisses (Feature 013).
 */
enum SafetyEventSeverity: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.safety.severity.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'ghost',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'error',
        };
    }
}
