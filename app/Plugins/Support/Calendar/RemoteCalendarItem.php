<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteCalendarItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

/**
 * Publizierbares Kalenderelement für den {@see RemoteCalendarPublishService}
 * (Konsolidierung C9): das Publish braucht nur stabile UID, lokale Herkunft
 * (Morph), Absage-Flag und Änderungs-Fingerprint. REST-Provider nutzen das
 * strukturierte {@see RemoteCalendarEvent}; CalDAV publiziert ICS-Objekte
 * ({@see \App\Plugins\CalDav\Services\CalendarPublishItem}) über denselben
 * Vertrag.
 */
interface RemoteCalendarItem {
    public function uid(): string;

    public function referenceableType(): string;

    public function referenceableId(): int;

    /** Abgesagt → extern entfernen statt publizieren. */
    public function cancelled(): bool;

    /** Änderungs-Fingerprint für das idempotente Publish (Hash-Vergleich). */
    public function fingerprint(): string;
}
