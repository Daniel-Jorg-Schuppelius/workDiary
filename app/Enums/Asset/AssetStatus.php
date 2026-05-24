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

enum AssetStatus: string {
    case Active = 'active';
    case InMaintenance = 'inMaintenance';
    case InRepair = 'inRepair';
    case Blocked = 'blocked';
    case Reserved = 'reserved';
    case LoanOut = 'loanOut';
    case Replaced = 'replaced';
    case Decommissioned = 'decommissioned';
    case Lost = 'lost';
}
