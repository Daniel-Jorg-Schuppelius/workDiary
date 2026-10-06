<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalConditionItemState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

/** Befund einer Checklistenposition im Übergabe- oder Rücknahmeprotokoll (MVP-263/265). */
enum RentalConditionItemState: string {
    case Ok = 'ok';
    case Worn = 'worn';
    case Damaged = 'damaged';
    case Missing = 'missing';
}
