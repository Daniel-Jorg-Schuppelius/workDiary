<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimVerdict.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Bewertungsergebnis (MVP-249): berechtigt / unklar / abgelehnt. */
enum ClaimVerdict: string implements HasLabel {
    use HasOptions;

    case Justified = 'justified';
    case Unclear = 'unclear';
    case Rejected = 'rejected';

    public function label(): string {
        return match ($this) {
            self::Justified => (string) __('Berechtigt'),
            self::Unclear => (string) __('Unklar'),
            self::Rejected => (string) __('Abgelehnt'),
        };
    }
}
