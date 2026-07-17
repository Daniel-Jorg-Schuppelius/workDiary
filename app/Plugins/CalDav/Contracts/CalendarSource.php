<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Contracts;

use App\Models\Organization;
use App\Plugins\CalDav\Services\CalendarPublishItem;

/**
 * Eine Quelle zu publizierender Kalenderelemente einer Organisation (Feature 058).
 * Das Publish ({@see \App\Plugins\Support\Calendar\RemoteCalendarPublishService}) ist
 * quellenneutral; jede Source (Termine, Dienstpläne/Urlaube …) liefert nur die Item-Liste.
 * Die Scope-Auswahl je Anbindung steuert {@see \App\Plugins\CalDav\CalDavPlugin::publishCalendar}.
 */
interface CalendarSource {
    /**
     * @return list<CalendarPublishItem>
     */
    public function itemsFor(Organization $organization): array;
}
