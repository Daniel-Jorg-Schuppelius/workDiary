<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Reporting;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bezugsebene eines Zielwerts: organisationsweit oder eingegrenzt auf einen
 * Kunden, ein Projekt oder einen Mitarbeitenden (scope_id verweist dann auf
 * die jeweilige ID). Org-weite Ziele dienen als Fallback, spezifischere Ziele
 * gewinnen ({@see App\Services\Reporting\ReportTargetEvaluator}).
 */
enum ReportTargetScope: string implements HasLabel {
    use HasOptions;

    case Org = 'org';
    case Customer = 'customer';
    case Project = 'project';
    case User = 'user';

    public function label(): string {
        return (string) __('reporting.target.scope.' . $this->value);
    }

    /** Spezifität für die Auswahl des „besten" Treffers (höher = spezifischer). */
    public function specificity(): int {
        return match ($this) {
            self::Org => 0,
            self::Customer, self::Project, self::User => 1,
        };
    }
}
