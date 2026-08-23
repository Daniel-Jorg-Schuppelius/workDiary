<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingObligationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Erledigungsstand einer Meldepflicht (Feature 125, MVP-686). */
enum FilingObligationStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';

    case Submitted = 'submitted';

    /** Bewusst nicht erforderlich — mit Begründung, nicht stillschweigend. */
    case NotRequired = 'not_required';

    public function label(): string {
        return (string) __('enums.finance.filing-obligation-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Submitted => 'success',
            self::NotRequired => 'neutral',
        };
    }

    public function isDone(): bool {
        return $this !== self::Open;
    }
}
