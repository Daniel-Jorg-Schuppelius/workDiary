<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenewalMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Verlängerung am Laufzeitende: automatisch (offenes Ende) oder gekündigt.
 */
enum RenewalMode: string implements HasLabel {
    use HasOptions;

    case Auto = 'auto';
    case Cancel = 'cancel';

    public function label(): string {
        return (string) __('resale.renewal.' . $this->value);
    }
}
