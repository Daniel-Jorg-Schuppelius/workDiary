<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadPageRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum LetterheadPageRole: string implements HasLabel {
    use HasOptions;

    case First = 'first';
    case Following = 'following';

    public function label(): string {
        return match ($this) {
            self::First => __('Erste Seite'),
            self::Following => __('Folgeseiten'),
        };
    }
}
