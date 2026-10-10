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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Sperrgrund im gemeinsamen Asset-Sperrmodell (Entscheidung D12).
 * Neue Sperrquellen ergänzen einen Grund, kein zweites Sperrmodell.
 */
enum AssetBlockReason: string implements HasLabel {
    use HasOptions;

    case Defect = 'defect';
    case Safety = 'safety';
    case Recall = 'recall';
    case InspectionOverdue = 'inspection_overdue';
    case InspectionFailed = 'inspection_failed';
    case RentalDamage = 'rental_damage';
    case PolicyHold = 'policy_hold';
    case Manual = 'manual';
    // Wartung/Instandsetzung (Boote, Geräte, Pferdeausrüstung — MVP-853).
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Defect => (string) __('enums.asset.asset_block_reason.defect'),
            self::Safety => (string) __('enums.asset.asset_block_reason.safety'),
            self::Recall => (string) __('enums.asset.asset_block_reason.recall'),
            self::InspectionOverdue => (string) __('enums.asset.asset_block_reason.inspection_overdue'),
            self::InspectionFailed => (string) __('enums.asset.asset_block_reason.inspection_failed'),
            self::RentalDamage => (string) __('enums.asset.asset_block_reason.rental_damage'),
            self::PolicyHold => (string) __('enums.asset.asset_block_reason.policy_hold'),
            self::Manual => (string) __('enums.asset.asset_block_reason.manual'),
            self::Maintenance => (string) __('enums.asset.asset_block_reason.maintenance'),
            self::Other => (string) __('enums.asset.asset_block_reason.other'),
        };
    }
}
