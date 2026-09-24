<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventOccurrenceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Events\Calendar\EventOccurrenceCreated;
use App\Models\Calendar\Event;

/** Kalenderkern: Serieninstanzen melden sich per Domain-Event; die Module erben ihre Daten dort (MVP-863). */
class EventOccurrenceObserver {
    public function created(Event $event): void {
        if ($event->series_id === null) {
            return;
        }

        EventOccurrenceCreated::dispatch($event);
    }
}
