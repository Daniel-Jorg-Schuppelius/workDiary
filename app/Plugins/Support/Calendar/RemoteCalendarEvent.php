<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteCalendarEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use CommonToolkit\Helper\Data\CryptoHelper;
use DateTimeImmutable;

/**
 * Ein zu publizierendes Kalenderelement für REST-Kalender-Provider
 * (MVP-328, Bauturbo A8): providerneutral strukturiert (Titel, Zeiten,
 * Ort) statt ICS, weil Microsoft Graph und Google Calendar JSON-Events
 * erwarten. Die stabile UID entspricht der CalDAV-/Feed-UID
 * ({@see \App\Services\Event\IcsFeedService::eventUid()}), damit alle
 * Kalender-Kanäle denselben Termin meinen. `cancelled` markiert ein
 * extern zu entfernendes Element (abgesagter Termin).
 */
final class RemoteCalendarEvent implements RemoteCalendarItem {
    public function __construct(
        public readonly string $uid,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $location,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly string $timezone,
        public readonly string $referenceableType,
        public readonly int $referenceableId,
        public readonly bool $cancelled = false,
    ) {}

    public function uid(): string {
        return $this->uid;
    }

    public function referenceableType(): string {
        return $this->referenceableType;
    }

    public function referenceableId(): int {
        return $this->referenceableId;
    }

    public function cancelled(): bool {
        return $this->cancelled;
    }

    /**
     * Änderungs-Fingerprint für das idempotente Publish (Hash-Vergleich in
     * der {@see \App\Models\ExternalReference}-Payload — CalDAV-Muster).
     */
    public function fingerprint(): string {
        return CryptoHelper::hash((string) json_encode([
            $this->title,
            $this->description,
            $this->location,
            $this->start->format(DATE_ATOM),
            $this->end->format(DATE_ATOM),
            $this->timezone,
        ]));
    }
}
