<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Timesheet;
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Notification\NotificationDispatcher;
use App\Support\CarbonFmt;

class TimesheetObserver {
    /**
     * Signatur-Benachrichtigung an den Besitzer über den zentralen
     * Dispatcher (B7). work_date ist reiner date-Cast — als ISO-Datum in den
     * Params rendert NotificationText ohne TZ-Umrechnung beim Betrachter.
     */
    public function updated(Timesheet $timesheet): void {
        if (! $timesheet->wasChanged('status')) {
            return;
        }

        // Lifecycle-Webhook timesheet.submitted (MVP-718): Web- und API-Controller
        // schreiben den Status direkt — der Modell-Statuswechsel ist die gemeinsame Naht.
        if ($timesheet->status === TimesheetStatus::Submitted) {
            app(LifecycleWebhookPublisher::class)->timesheetSubmitted($timesheet);

            return;
        }

        if ($timesheet->status !== TimesheetStatus::Signed) {
            return;
        }

        $timesheet->loadMissing(['user', 'project']);
        $owner = $timesheet->user;
        $project = $timesheet->project;
        if ($owner === null || $project === null) {
            return;
        }

        $params = [
            'project' => (string) $project->name,
            'date' => $timesheet->work_date->toDateString(),
        ];
        app(NotificationDispatcher::class)->notify(NotificationEvent::TimesheetSigned, $timesheet, $owner, [
            'title' => (string) __('notification.message.timesheet_signed_title'),
            'title_key' => 'notification.message.timesheet_signed_title',
            'title_params' => [],
            'message' => (string) __('notification.message.timesheet_signed', [
                ...$params,
                'date' => CarbonFmt::fdate($timesheet->work_date),
            ]),
            'message_key' => 'notification.message.timesheet_signed',
            'message_params' => $params,
            'url' => route('projects.timesheets.show', [$project, $timesheet]),
        ]);
    }
}
