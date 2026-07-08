<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Providerneutraler Kalender-Publish-Vertrag (Feature 058, MVP-126): ein Plugin
 * mit {@see PluginCapability::CalendarPublish} **publiziert** WorkDiary-Termine
 * und -Dienstpläne in einen externen Kalender (z. B. CalDAV/Nextcloud). WorkDiary
 * bleibt führend; Anlegen/Ändern/Löschen laufen **idempotent** über stabile UIDs
 * — abgesagte Termine werden entfernt, wiederholte Läufe erzeugen keine Dubletten.
 * Rückimport externer Termine ist bewusst zweite Ausbaustufe.
 */
interface CalendarPublisher {
    /**
     * Publiziert die relevanten Termine/Dienstpläne der Organisation in den
     * bzw. die konfigurierten externen Kalender (idempotent).
     *
     * @return array{published: int, deleted: int, unchanged: int, failed: int}
     */
    public function publishCalendar(Organization $organization): array;
}
