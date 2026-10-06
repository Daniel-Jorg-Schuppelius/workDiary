<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceInboxStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

/**
 * Zuordnungsstand einer gespiegelten Marktplatz-Bestellung (Billbee, Etsy):
 * offen, solange der Käufer keinem Kunden zugeordnet ist.
 */
enum MarketplaceInboxStatus: string {
    case Open = 'open';
    case Linked = 'linked';
}
