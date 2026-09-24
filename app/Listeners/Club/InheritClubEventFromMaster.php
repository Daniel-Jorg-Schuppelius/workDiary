<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InheritClubEventFromMaster.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Club;

use App\Events\Calendar\EventOccurrenceCreated;
use App\Listeners\ModuleListener;
use App\Services\Club\ClubEventService;

final class InheritClubEventFromMaster extends ModuleListener {
    public function __construct(private readonly ClubEventService $events) {}

    protected function module(): string {
        return 'club';
    }

    public function handle(EventOccurrenceCreated $event): void {
        if (! $this->shouldHandle($event->event->organization_id)) {
            return;
        }
        $this->events->inheritFromMaster($event->event);
    }
}
