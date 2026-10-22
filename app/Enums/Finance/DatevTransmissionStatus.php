<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTransmissionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Übermittlung eines exportierten DATEV-Stapels (MVP-1277). Der Stapel selbst
 * ist festgeschrieben; der Stand ergibt sich aus seinem Journal (`transmitted`,
 * `confirmed`, `rejected`). Ein abgelehnter Stapel wird nach Klärung erneut
 * übermittelt.
 */
enum DatevTransmissionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Transmitted = 'transmitted';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.finance.datev-transmission-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'neutral',
            self::Transmitted => 'info',
            self::Confirmed => 'success',
            self::Rejected => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Transmitted],
            self::Transmitted => [self::Confirmed, self::Rejected],
            self::Rejected => [self::Transmitted],
            self::Confirmed => [],
        };
    }

    /** Journal-Ereignis, das diesen Stand setzt; Open hat keins. */
    public function event(): ?string {
        return $this === self::Open ? null : $this->value;
    }
}
