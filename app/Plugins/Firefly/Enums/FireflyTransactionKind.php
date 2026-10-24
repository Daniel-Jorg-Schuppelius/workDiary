<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyTransactionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer Firefly-Bewegung; Umbuchungen und Eröffnungssalden bleiben außen vor. */
enum FireflyTransactionKind: string implements HasLabel {
    use HasOptions;

    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function label(): string {
        return (string) __('firefly::enums.transaction_kind.' . $this->value);
    }
}
