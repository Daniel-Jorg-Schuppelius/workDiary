<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kritikalität eines Vorfalls bzw. einer Schwachstelle (Feature 044, MVP 2).
 * Bei Schwachstellen aus einem CVSS-v3-Basisscore ableitbar
 * ({@see self::fromCvss()}, CVSS-v3-Schwellen 0.1/4.0/7.0/9.0).
 */
enum IncidentSeverity: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.isms.incident-severity.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'error',
            self::Critical => 'error',
        };
    }

    /**
     * Severity-Einstufung aus einem CVSS-v3-Basisscore (0.0–10.0) entlang
     * der offiziellen qualitativen Schwellen.
     */
    public static function fromCvss(float $score): self {
        return match (true) {
            $score >= 9.0 => self::Critical,
            $score >= 7.0 => self::High,
            $score >= 4.0 => self::Medium,
            default => self::Low,
        };
    }
}
