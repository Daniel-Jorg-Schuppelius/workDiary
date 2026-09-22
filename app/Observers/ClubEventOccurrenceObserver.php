<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventOccurrenceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\Event;
use App\Services\Club\ClubEventService;

/**
 * Nachträglich materialisierte Serienvorkommen (Scheduler, Feature 028)
 * erben die Vereinsdetails und Zielgruppen ihres Masters (MVP-843) — sonst
 * hätte jeder Serientermin nach dem Fenster keine Soll-Liste mehr.
 */
class ClubEventOccurrenceObserver {
    public function created(Event $event): void {
        if ($event->series_id === null) {
            return;
        }

        app(ClubEventService::class)->inheritFromMaster($event);
    }
}
