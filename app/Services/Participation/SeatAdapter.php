<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeatAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Participation;

use App\Models\Calendar\Event;

/**
 * Teilnehmerart mit Platzbedarf am Termin (MVP-843): Benutzer (`event_user`)
 * und Vereinsmitglieder (`club_event_participations`) teilen sich Kapazität
 * und Warteliste — der Algorithmus lebt einmal im EventSeatService.
 */
interface SeatAdapter {
    /** Belegte Plätze dieser Teilnehmerart. */
    public function takenSeats(Event $event): int;

    /** Ältester Wartelisten-Eintrag dieser Teilnehmerart oder null. */
    public function nextWaitlisted(Event $event): ?WaitlistCandidate;
}
