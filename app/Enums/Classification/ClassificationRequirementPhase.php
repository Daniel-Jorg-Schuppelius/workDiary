<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementPhase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Classification;

enum ClassificationRequirementPhase: string {
    case OnCreate = 'onCreate';
    case BeforeComplete = 'beforeComplete';
    case BeforeSign = 'beforeSign';
}
