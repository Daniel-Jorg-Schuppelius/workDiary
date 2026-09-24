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
            self::Active => (string) __('Aktiv'),
            self::InMaintenance => (string) __('In Wartung'),
            self::InRepair => (string) __('In Reparatur'),
            self::Blocked => (string) __('Gesperrt'),
            self::Reserved => (string) __('Reserviert'),
            self::LoanOut => (string) __('Ausgeliehen'),
            self::Replaced => (string) __('Ersetzt'),
            self::Decommissioned => (string) __('Außer Betrieb'),
            self::Lost => (string) __('Verloren'),
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
