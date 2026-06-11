<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControlSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft eines Controls: Annex-A-Referenzkatalog oder eigene Maßnahme. */
enum ControlSource: string implements HasLabel {
    use HasOptions;

    case Iso27001AnnexA = 'iso27001AnnexA';
    case Custom = 'custom';

    public function label(): string {
        return (string) __('enums.isms.control-source.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Iso27001AnnexA => 'info',
            self::Custom => 'ghost',
        };
    }
}
