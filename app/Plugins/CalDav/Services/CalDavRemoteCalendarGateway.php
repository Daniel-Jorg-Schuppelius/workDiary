<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavRemoteCalendarGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Plugins\CalDav\Contracts\CalDavGateway;
use App\Plugins\Support\Calendar\{RemoteCalendarGateway, RemoteCalendarItem};

/**
 * Adaptiert das CalDAV-Gateway an den gemeinsamen Kalender-Vertrag
 * (Konsolidierung C9): create/update → PUT auf den deterministischen
 * Objektnamen des Items (die Remote-ID neuer Referenzen), delete → DELETE
 * auf die tolerant gelesene Remote-ID (Alt: Payload `object`, Neu:
 * `external_id`). ICS-Rendering bleibt CalDAV-lokal (Quellen/Items).
 */
final class CalDavRemoteCalendarGateway implements RemoteCalendarGateway {
    public function __construct(private readonly CalDavGateway $gateway) {}

    public function createEvent(RemoteCalendarItem $event): ?string {
        if (! $event instanceof CalendarPublishItem) {
            return null; // dieses Gateway publiziert nur ICS-Objekte
        }

        return $this->gateway->putObject($event->objectName, $event->ics) ? $event->objectName : null;
    }

    /** PUT immer auf den deterministischen Objektnamen des Items (Alt-Verhalten). */
    public function updateEvent(string $remoteId, RemoteCalendarItem $event): bool {
        if (! $event instanceof CalendarPublishItem) {
            return false;
        }

        return $this->gateway->putObject($event->objectName, $event->ics);
    }

    public function deleteEvent(string $remoteId): bool {
        return $this->gateway->deleteObject($remoteId);
    }

    /** CalDAV: die Ziel-Collection ist konfiguriert, keine Kalenderliste. */
    public function listCalendars(): array {
        return [];
    }

    public function ping(): bool {
        return $this->gateway->ping();
    }
}
