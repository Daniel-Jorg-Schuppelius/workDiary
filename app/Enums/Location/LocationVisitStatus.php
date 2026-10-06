<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationVisitStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Location;

/** Stand eines Geofence-Besuchs: offen, solange der Nutzer im Bereich ist, danach geschlossen. */
enum LocationVisitStatus: string {
    case Open = 'open';
    case Closed = 'closed';
}
