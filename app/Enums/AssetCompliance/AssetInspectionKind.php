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

/**
 * Prüfart (MVP-283): welche Pflicht im Einzelfall gilt, entscheidet der
 * Betrieb — WorkDiary macht keine Rechtsberatung (W12).
 */
enum AssetInspectionKind: string {
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
            self::Verification => (string) __('Eichung'),
            self::Calibration => (string) __('Kalibrierung'),
            self::DguvUvv => (string) __('DGUV-/UVV-Prüfung'),
            self::HuAu => (string) __('HU/AU'),
            self::Electrical => (string) __('Elektrische Betriebsmittelprüfung'),
            self::ManufacturerService => (string) __('Herstellerwartung'),
            self::SafetyCheck => (string) __('Sicherheitsprüfung'),
            self::FunctionCheck => (string) __('Funktionsprüfung'),
            self::InternalCheck => (string) __('Interne Kontrollprüfung'),
        };
    }
}
