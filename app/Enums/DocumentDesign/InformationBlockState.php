<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InformationBlockState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum InformationBlockState: string implements HasLabel {
    use HasOptions;

    case Dynamic = 'dynamic';
    case ProvidedByLetterhead = 'provided_by_letterhead';
    case NotApplicable = 'not_applicable';

    public function label(): string {
        return match ($this) {
            self::Dynamic => __('Dynamisch (WorkDiary druckt)'),
            self::ProvidedByLetterhead => __('Bereits auf dem Firmenbogen'),
            self::NotApplicable => __('Nicht anwendbar'),
        };
    }
}
