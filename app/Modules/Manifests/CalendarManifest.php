<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Termine und Kalender“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CalendarManifest extends Manifest {
    public function code(): string {
        return 'calendar';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Termine und Kalender';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Calendar',
            'Event',
            'Appointments',
            'Participation',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'appointment_requests',
            'availability_windows',
            'event_categories',
            'event_reminders',
            'event_room',
            'event_user',
            'events',
            'holidays',
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'caldav',
            'msgraph',
            'google_calendar',
            'calendly',
        ];
    }
}
