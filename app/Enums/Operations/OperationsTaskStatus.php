<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTaskStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Operations;

/**
 * Status einer Betriebsaufgabe (Feature 041, MVP-058).
 * done = manuell erledigt; resolved = Ursache weggefallen (Auto-Resolve
 * durch den Scanner); ignored = bewusst verworfen (mit Grund).
 */
enum OperationsTaskStatus: string {
    case Open = 'open';
    case Snoozed = 'snoozed';
    case Delegated = 'delegated';
    case Ignored = 'ignored';
    case Done = 'done';
    case Resolved = 'resolved';

    public function label(): string {
        return __('operations.status.' . $this->value);
    }

    public function isActive(): bool {
        return in_array($this, [self::Open, self::Snoozed, self::Delegated], true);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Snoozed => 'neutral',
            self::Delegated => 'info',
            self::Ignored => 'ghost',
            self::Done, self::Resolved => 'success',
        };
    }
}
