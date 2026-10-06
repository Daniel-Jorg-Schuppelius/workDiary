<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimAssessmentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/** Geltung einer Bewertung (MVP-249): je Fall genau eine aktive, jede neue löst die vorige ab. */
enum ClaimAssessmentStatus: string {
    case Active = 'active';
    case Superseded = 'superseded';
}
