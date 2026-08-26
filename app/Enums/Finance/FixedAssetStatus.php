<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status einer Anlage im Anlagenregister (Feature 133, MVP-698):
 * `active → disposed`. Der Abgang ist final — eine „Wiederaufnahme" wäre
 * eine neue Anlage mit eigener Nummer.
 */
enum FixedAssetStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Active = 'active';
    case Disposed = 'disposed';

    public function label(): string {
        return (string) __('enums.finance.fixed-asset-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Disposed => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Active => [self::Disposed],
            self::Disposed => [],
        };
    }
}
