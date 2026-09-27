<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Anlass einer Rückrufaktion (MVP-921). */
enum RecallKind: string implements HasLabel {
    use HasOptions;

    case Safety = 'safety';
    case Quality = 'quality';
    case Regulatory = 'regulatory';

    public function label(): string {
        return (string) __('recall.kind.' . $this->value);
    }
}
