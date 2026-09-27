<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallMeasure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Ergriffene Maßnahme eines Rückrufs für die Behördenmeldung (MVP-945). */
enum RecallMeasure: string implements HasLabel {
    use HasOptions;

    case Withdrawal = 'withdrawal';
    case Recall = 'recall';
    case Warning = 'warning';
    case Destruction = 'destruction';

    public function label(): string {
        return (string) __('recall.authority.measure.' . $this->value);
    }
}
