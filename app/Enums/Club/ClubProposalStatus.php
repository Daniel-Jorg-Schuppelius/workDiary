<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubProposalStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Wechsel-/Prüfvorschlags (MVP-842): offen, bestätigt (mit
 * Wirksamkeitsdatum) oder verworfen — nie automatisch umgesetzt.
 */
enum ClubProposalStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';

    public function label(): string {
        return (string) __('enums.club.proposal-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Confirmed => 'success',
            self::Dismissed => 'ghost',
        };
    }
}
