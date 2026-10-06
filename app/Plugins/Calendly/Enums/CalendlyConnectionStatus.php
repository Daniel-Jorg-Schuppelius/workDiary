<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Enums;

/** Stand der Calendly-Verbindung einer Organisation (Feature 095). */
enum CalendlyConnectionStatus: string {
    case Active = 'active';
    case Disconnected = 'disconnected';
}
