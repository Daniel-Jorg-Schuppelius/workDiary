<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\KeyHandover;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum KeyHandoverDirection: string implements HasLabel {
    use HasOptions;

    case Out = 'out';
    case In = 'in';

    public function label(): string {
        return match ($this) {
            self::Out => __('Ausgabe'),
            self::In => __('Rückgabe'),
        };
    }
}
