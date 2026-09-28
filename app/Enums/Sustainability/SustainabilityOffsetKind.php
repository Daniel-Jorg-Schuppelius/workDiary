<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityOffsetKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sustainability;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art eines Klimanachweises (MVP-961). */
enum SustainabilityOffsetKind: string implements HasLabel {
    use HasOptions;

    case Compensation = 'compensation';
    case GreenEnergy = 'green_energy';
    case Contribution = 'contribution';

    public function label(): string {
        return (string) __('sustainability.offset.kind.' . $this->value);
    }
}
