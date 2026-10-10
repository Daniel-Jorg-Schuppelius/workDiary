<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

enum AssetStatus: string implements HasLabel, HasStatusTransitions {
    use HasTransitions;
    case Active = 'active';
    case InMaintenance = 'inMaintenance';
    case InRepair = 'inRepair';
    case Blocked = 'blocked';
    case Reserved = 'reserved';
    case LoanOut = 'loanOut';
    case Replaced = 'replaced';
    case Decommissioned = 'decommissioned';
    case Lost = 'lost';

    public function label(): string {
        return match ($this) {
            self::Active => (string) __('enums.asset.asset_status.active'),
            self::InMaintenance => (string) __('enums.asset.asset_status.in_maintenance'),
            self::InRepair => (string) __('enums.asset.asset_status.in_repair'),
            self::Blocked => (string) __('enums.asset.asset_status.blocked'),
            self::Reserved => (string) __('enums.asset.asset_status.reserved'),
            self::LoanOut => (string) __('enums.asset.asset_status.loan_out'),
            self::Replaced => (string) __('enums.asset.asset_status.replaced'),
            self::Decommissioned => (string) __('enums.asset.asset_status.decommissioned'),
            self::Lost => (string) __('enums.asset.asset_status.lost'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Active => [self::InMaintenance, self::InRepair, self::Blocked, self::Reserved, self::LoanOut, self::Replaced, self::Decommissioned, self::Lost],
            self::InMaintenance => [self::Active, self::InRepair, self::Blocked, self::Decommissioned],
            self::InRepair => [self::Active, self::Blocked, self::Decommissioned, self::Replaced],
            self::Blocked => [self::Active, self::InRepair, self::Decommissioned, self::Replaced],
            self::Reserved => [self::Active, self::LoanOut, self::Blocked, self::Decommissioned],
            self::LoanOut => [self::Active, self::Blocked, self::Lost, self::Decommissioned],
            self::Replaced => [self::Decommissioned],
            self::Decommissioned => [],
            self::Lost => [self::Active, self::Decommissioned],
        };
    }
}
