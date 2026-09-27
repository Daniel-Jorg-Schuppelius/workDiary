<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallRiskLevel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Risikostufe eines Rückrufs für die Behördenmeldung (MVP-945). */
enum RecallRiskLevel: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Serious = 'serious';

    public function label(): string {
        return (string) __('recall.authority.risk.' . $this->value);
    }
}
