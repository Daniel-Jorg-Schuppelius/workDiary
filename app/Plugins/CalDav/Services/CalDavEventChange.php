<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavEventChange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

/**
 * Ein geändertes Kalenderobjekt aus dem CalDAV-Delta (Feature 121, MVP-610b):
 * href, ETag und das rohe iCalendar. Das Parsen bleibt beim Importer — das
 * Gateway liefert nur, was auf der Leitung stand.
 */
final class CalDavEventChange {
    public function __construct(
        public readonly string $href,
        public readonly string $etag,
        public readonly string $ics,
    ) {}

    /** Objektname = letztes Pfadsegment; er ist die Remote-ID des Publishs. */
    public function objectName(): string {
        return rawurldecode(basename($this->href));
    }
}
