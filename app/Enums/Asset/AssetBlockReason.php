<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlockReason.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Asset;

/**
 * Sperrgrund im gemeinsamen Asset-Sperrmodell (Entscheidung D12).
 * Neue Sperrquellen ergänzen einen Grund, kein zweites Sperrmodell.
 */
enum AssetBlockReason: string {
    case Defect = 'defect';
    case Safety = 'safety';
    case Recall = 'recall';
    case InspectionOverdue = 'inspection_overdue';
    case InspectionFailed = 'inspection_failed';
    case RentalDamage = 'rental_damage';
    case PolicyHold = 'policy_hold';
    case Manual = 'manual';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Defect => (string) __('Defekt'),
            self::Safety => (string) __('Arbeitsschutz'),
            self::Recall => (string) __('Rückruf'),
            self::InspectionOverdue => (string) __('Prüfung überfällig'),
            self::InspectionFailed => (string) __('Prüfung nicht bestanden'),
            self::RentalDamage => (string) __('Verleihschaden'),
            self::PolicyHold => (string) __('Interne Sperre'),
            self::Manual => (string) __('Manuell gesperrt'),
            self::Other => (string) __('Sonstiger Grund'),
        };
    }
}
