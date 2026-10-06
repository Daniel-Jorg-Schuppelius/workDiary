<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTaskListLinkStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Enums;

/** Stand einer To-Do-Listen-Zuordnung: nur aktive Zuordnungen werden synchronisiert. */
enum MsgraphTaskListLinkStatus: string {
    case Active = 'active';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Paused = 'paused';
}
