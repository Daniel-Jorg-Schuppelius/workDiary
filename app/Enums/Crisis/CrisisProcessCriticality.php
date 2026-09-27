<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisProcessCriticality.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Crisis;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Kritikalität eines Geschäftsprozesses im BIA-Register (MVP-943). */
enum CrisisProcessCriticality: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('crisis.bia.criticality.' . $this->value);
    }
}
