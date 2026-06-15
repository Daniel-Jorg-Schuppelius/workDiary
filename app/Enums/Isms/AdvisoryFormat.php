<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdvisoryFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Format eines maschinenlesbaren Advisories (Feature 044, MVP 2):
 * CSAF (Common Security Advisory Framework, JSON) bzw. ein VEX-Profil
 * (Vulnerability Exploitability eXchange — als CSAF-VEX-Profil oder
 * CycloneDX-VEX). Beide werden nativ per json_decode geparst.
 */
enum AdvisoryFormat: string implements HasLabel {
    use HasOptions;

    case Csaf = 'csaf';
    case Vex = 'vex';

    public function label(): string {
        return (string) __('enums.isms.advisory-format.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Csaf => 'primary',
            self::Vex => 'accent',
        };
    }
}
