<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Prüfart (MVP-283): welche Pflicht im Einzelfall gilt, entscheidet der
 * Betrieb — WorkDiary macht keine Rechtsberatung (W12).
 */
enum AssetInspectionKind: string implements HasLabel {
    use HasOptions;

    case Verification = 'verification';
    case Calibration = 'calibration';
    case DguvUvv = 'dguv_uvv';
    case HuAu = 'hu_au';
    case Electrical = 'electrical';
    case ManufacturerService = 'manufacturer_service';
    case SafetyCheck = 'safety_check';
    case FunctionCheck = 'function_check';
    case InternalCheck = 'internal_check';

    public function label(): string {
        return match ($this) {
            self::Verification => (string) __('enums.asset_compliance.asset_inspection_kind.verification'),
            self::Calibration => (string) __('enums.asset_compliance.asset_inspection_kind.calibration'),
            self::DguvUvv => (string) __('enums.asset_compliance.asset_inspection_kind.dguv_uvv'),
            self::HuAu => (string) __('enums.asset_compliance.asset_inspection_kind.hu_au'),
            self::Electrical => (string) __('enums.asset_compliance.asset_inspection_kind.electrical'),
            self::ManufacturerService => (string) __('enums.asset_compliance.asset_inspection_kind.manufacturer_service'),
            self::SafetyCheck => (string) __('enums.asset_compliance.asset_inspection_kind.safety_check'),
            self::FunctionCheck => (string) __('enums.asset_compliance.asset_inspection_kind.function_check'),
            self::InternalCheck => (string) __('enums.asset_compliance.asset_inspection_kind.internal_check'),
        };
    }
}
