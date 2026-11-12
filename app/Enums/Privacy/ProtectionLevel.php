<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtectionLevel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Schutzbedarf, den eine TOM abdeckt (Feature 043; Stufen nach BSI-Standard 200-2). */
enum ProtectionLevel: string implements HasLabel {
    use HasOptions;

    case Normal = 'normal';
    case High = 'high';
    case VeryHigh = 'very_high';

    public function label(): string {
        return (string) __('enums.privacy.protection_level.' . $this->value);
    }
}
