<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirementKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Facility;

/**
 * Raumbezogene fachliche Anforderung je Gewerk (Feature 027).
 *
 * Ergänzt das Reinigungsprofil (CleaningProfile) um weitere gewerkbezogene
 * Anforderungen, die ein Raum gleichzeitig tragen kann, ohne ihn doppelt
 * anzulegen (1:n über `room_requirements`).
 */
enum RoomRequirementKind: string {
    case HygieneLevel = 'hygieneLevel';
    case SpecialCleaning = 'specialCleaning';
    case AccessRestriction = 'accessRestriction';
    case ItInventory = 'itInventory';
    case TechnicalInspection = 'technicalInspection';
    case OperatorDuty = 'operatorDuty';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::HygieneLevel => __('enums.room_requirement_kind.hygieneLevel'),
            self::SpecialCleaning => __('enums.room_requirement_kind.specialCleaning'),
            self::AccessRestriction => __('enums.room_requirement_kind.accessRestriction'),
            self::ItInventory => __('enums.room_requirement_kind.itInventory'),
            self::TechnicalInspection => __('enums.room_requirement_kind.technicalInspection'),
            self::OperatorDuty => __('enums.room_requirement_kind.operatorDuty'),
            self::Other => __('enums.room_requirement_kind.other'),
        };
    }

    public function icon(): string {
        return match ($this) {
            self::HygieneLevel => 'sanitizer',
            self::SpecialCleaning => 'cleaning_services',
            self::AccessRestriction => 'lock',
            self::ItInventory => 'dns',
            self::TechnicalInspection => 'engineering',
            self::OperatorDuty => 'gavel',
            self::Other => 'label',
        };
    }

    /** @return array<string, string> */
    public static function options(): array {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
