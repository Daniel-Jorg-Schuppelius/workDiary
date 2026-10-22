<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProposalStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Tour;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Lebenslauf eines Tourvorschlags (MVP-1176): berechnet wird er, dann übernommen oder verworfen. */
enum TourProposalStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Computing = 'computing';
    case Open = 'open';
    case Adopted = 'adopted';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.tour.proposal_status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Computing => [self::Open, self::Failed],
            self::Open => [self::Adopted, self::Rejected],
            self::Adopted, self::Rejected, self::Failed => [],
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Computing => 'info',
            self::Open => 'warning',
            self::Adopted => 'success',
            self::Rejected, self::Failed => 'ghost',
        };
    }
}
