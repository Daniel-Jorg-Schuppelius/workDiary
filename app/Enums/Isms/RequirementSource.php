<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Normanforderung (Feature 046): Referenzkatalog
 * (z. B. ISO/IEC 27001:2022 Annex A) oder eigene Anforderung.
 * Nachfolger von ControlSource (Feature 044, vor dem Kern-Refactoring).
 */
enum RequirementSource: string implements HasLabel {
    use HasOptions;

    case Catalog = 'catalog';
    case Custom = 'custom';

    public function label(): string {
        return (string) __('enums.isms.requirement-source.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Catalog => 'info',
            self::Custom => 'ghost',
        };
    }
}
