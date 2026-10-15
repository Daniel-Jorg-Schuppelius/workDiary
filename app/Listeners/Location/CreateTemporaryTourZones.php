<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreateTemporaryTourZones.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Location;

use App\Events\Tour\TourStarted;
use App\Listeners\ModuleListener;
use App\Services\Location\ZoneFactory;

/**
 * Beim Tourstart entstehen temporäre Zonen an den Stopp-Adressen (MVP-1181),
 * damit die Standorterfassung ohne Zonenpflege ein Ist je Stopp liefert.
 */
final class CreateTemporaryTourZones extends ModuleListener {
    public function __construct(private readonly ZoneFactory $zones) {}

    protected function module(): string {
        return 'location';
    }

    public function handle(TourStarted $event): void {
        if (! $this->shouldHandle($event->tour->organization_id)) {
            return;
        }
        $this->zones->forTour($event->tour);
    }
}
