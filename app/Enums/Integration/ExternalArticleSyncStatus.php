<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalArticleSyncStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Contracts\HasLabel;
use App\Support\Trans;

/**
 * Stand der Zuordnung eines externen Artikels (Feature 048, MVP-060):
 * „pending“ ohne lokalen Artikel, sonst „linked“ (JTL, Lexoffice) bzw.
 * „synced“ (Billbee, Etsy).
 */
enum ExternalArticleSyncStatus: string implements HasLabel {
    case Pending = 'pending';
    case Linked = 'linked';
    case Synced = 'synced';

    public function label(): string {
        return Trans::or('values.' . $this->value, $this->value);
    }
}
