<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Prüfergebnis (MVP-286): keine automatische Freigabe ohne fachliche
 * Prüferentscheidung. Nicht bestanden sperrt über das gemeinsame Modell.
 */
enum AssetInspectionResult: string implements HasLabel {
    use HasOptions;

    case Passed = 'passed';
    case PassedWithRestrictions = 'passed_with_restrictions';
    case Failed = 'failed';

    public function label(): string {
        return match ($this) {
            self::Passed => (string) __('enums.asset_compliance.asset_inspection_result.passed'),
            self::PassedWithRestrictions => (string) __('enums.asset_compliance.asset_inspection_result.passed_with_restrictions'),
            self::Failed => (string) __('enums.asset_compliance.asset_inspection_result.failed'),
        };
    }

    public function isPassed(): bool {
        return $this !== self::Failed;
    }
}
