<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenslauf eines Abos: aktiv → gekündigt (Ende bekannt) / abgelöst
 * (Nachfolger beim anderen Anbieter) / beendet (Ende erreicht).
 */
enum SubscriptionStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
    case Ended = 'ended';

    public function label(): string {
        return (string) __('resale.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'warning',
            self::Superseded => 'info',
            self::Ended => 'neutral',
        };
    }

    /** Beendete und abgelöste Abos bekommen keine neuen Perioden mehr. */
    public function isPlanning(): bool {
        return $this === self::Active || $this === self::Cancelled;
    }
}
