<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTaskSeverity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Operations;

/**
 * Dringlichkeit einer Betriebsaufgabe (Feature 041, MVP-058).
 * Default-Routing: critical → Aufgabe + Benachrichtigung,
 * warning → Aufgabe (+ Benachrichtigung gemäß Regel), info → nur Meldung.
 */
enum OperationsTaskSeverity: string {
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string {
        return __('operations.severity.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'error',
        };
    }

    public function rank(): int {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }
}
