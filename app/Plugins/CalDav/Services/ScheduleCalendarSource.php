<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleCalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Models\{Organization, ScheduledShift, Vacation};
use App\Plugins\CalDav\Contracts\CalendarSource;
use App\Services\Event\IcsFeedService;

/**
 * Liefert die zu publizierenden Kalenderelemente aus den Dienstplänen
 * ({@see ScheduledShift}) und Urlauben ({@see Vacation}) einer Organisation
 * (Feature 058, Rang 17). Fenster wie der persönliche Schedule-Feed
 * (Schichten −2 Monate, Urlaube −1 Jahr). Nur veröffentlichte/bestätigte
 * Schichten mit Zeiten bzw. genehmigte Urlaube werden publiziert; alles andere
 * (Absage, Rücknahme, fehlende Zeiten) wird als `cancelled` markiert, damit ein
 * zuvor extern angelegtes Objekt wieder entfernt wird. UID/ICS stammen aus dem
 * {@see IcsFeedService} — eine einzige Abbildung für Feed und CalDAV.
 */
class ScheduleCalendarSource implements CalendarSource {
    public function __construct(private readonly IcsFeedService $ics) {}

    /**
     * @return list<CalendarPublishItem>
     */
    public function itemsFor(Organization $organization): array {
        $items = [];

        $vacations = Vacation::query()
            ->where('organization_id', $organization->id)
            ->where('end_date', '>=', now()->subYear()->toDateString())
            ->with('user')
            ->orderBy('start_date')
            ->get();

        foreach ($vacations as $vacation) {
            // Nur genehmigte Urlaube werden publiziert; Rücknahme/Ablehnung → entfernen.
            $cancelled = $vacation->status !== VacationStatus::Approved;
            $items[] = new CalendarPublishItem(
                uid: IcsFeedService::vacationUid($vacation),
                objectName: 'vacation-' . $vacation->sqid . '.ics',
                ics: $cancelled ? '' : $this->ics->documentForVacation($vacation),
                referenceableType: $vacation->getMorphClass(),
                referenceableId: (int) $vacation->getKey(),
                cancelled: $cancelled,
            );
        }

        $shifts = ScheduledShift::query()
            ->where('organization_id', $organization->id)
            ->where('date', '>=', now()->subMonths(2)->toDateString())
            ->with('shiftType')
            ->orderBy('date')
            ->get();

        foreach ($shifts as $shift) {
            // Publizierbar nur mit Zeiten und im Status Published/Confirmed;
            // Draft/Cancelled/zeitlos → entfernen (bzw. nie anlegen).
            $publishable = $shift->start_time && $shift->end_time
                && in_array($shift->status, [ScheduledShiftStatus::Published, ScheduledShiftStatus::Confirmed], true);
            $cancelled = ! $publishable;
            $items[] = new CalendarPublishItem(
                uid: IcsFeedService::shiftUid($shift),
                objectName: 'shift-' . $shift->sqid . '.ics',
                ics: $cancelled ? '' : $this->ics->documentForShift($shift),
                referenceableType: $shift->getMorphClass(),
                referenceableId: (int) $shift->getKey(),
                cancelled: $cancelled,
            );
        }

        return $items;
    }
}
