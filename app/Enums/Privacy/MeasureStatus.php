<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Stand einer Folgemaßnahme zu Vorfall oder DSFA: offen bis zur Erledigung. */
enum MeasureStatus: string {
    case Open = 'open';
    case Done = 'done';
}
