<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Statusmodell des allgemeinen Vertrags (Welle D, CLM). Terminated
 * (gekündigt) läuft bis zum Vertragsende weiter — deshalb noch „offen".
 */
enum ContractStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Terminated = 'terminated';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('Entwurf'),
            self::Active => (string) __('Aktiv'),
            self::Terminated => (string) __('Gekündigt'),
            self::Ended => (string) __('Beendet'),
            self::Cancelled => (string) __('Storniert'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Terminated, self::Ended],
            self::Terminated => [self::Ended],
            self::Ended, self::Cancelled => [],
        };
    }

    /** Noch laufend/verwaltbar (Draft/Active/Terminated bis zum Ende). */
    public function isOpen(): bool {
        return in_array($this, [self::Draft, self::Active, self::Terminated], true);
    }
}
