<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Notification\NotificationEvent;
use App\Models\{EmergencyAssignment, User};
use App\Services\Notification\NotificationDispatcher;
use App\Support\CarbonFmt;

class EmergencyAssignmentObserver {
    /**
     * Notdienst-Zuweisung über den zentralen Dispatcher (B7): der Zeitpunkt
     * wandert als ISO-Wert in die Params — NotificationText rendert ihn erst
     * beim Betrachter (Locale/Zeitzone des Empfängers statt des Auslösers).
     */
    public function created(EmergencyAssignment $assignment): void {
        $assignment->loadMissing('user');
        $user = $assignment->user;
        if (! $user instanceof User) {
            return;
        }

        $params = [
            'start' => $assignment->start_at->toIso8601String(),
            'reason' => (string) ($assignment->reason ?: ''),
        ];
        app(NotificationDispatcher::class)->notify(NotificationEvent::EmergencyAssigned, $assignment, $user, [
            'title' => (string) __('notification.message.emergency_assigned_title'),
            'title_key' => 'notification.message.emergency_assigned_title',
            'title_params' => [],
            'message' => (string) __('notification.message.emergency_assigned', [
                ...$params,
                'start' => CarbonFmt::fdatetime($assignment->start_at),
            ]),
            'message_key' => 'notification.message.emergency_assigned',
            'message_params' => $params,
            'url' => route('week.index'),
        ]);
    }
}
