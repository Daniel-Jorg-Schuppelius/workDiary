<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Indexanpassung eines Vertrags (MVP-952): Vorschlag → übernommen oder verworfen. */
enum ContractIndexationStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Proposed = 'proposed';
    case Applied = 'applied';
    case Dismissed = 'dismissed';

    public function label(): string {
        return (string) __('contract.indexation.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Proposed => 'warning',
            self::Applied => 'success',
            self::Dismissed => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Proposed => [self::Applied, self::Dismissed],
            self::Applied, self::Dismissed => [],
        };
    }
}
