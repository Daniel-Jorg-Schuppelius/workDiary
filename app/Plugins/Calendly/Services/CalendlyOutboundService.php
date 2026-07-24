<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyOutboundService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Models\CalendlyConnection;
use App\Plugins\Calendly\Api\CalendlyClient;
use Carbon\CarbonImmutable;

/**
 * Ausgehende Calendly-Aktionen (Feature 095, bidirektional): Einmal-Buchungslink
 * je Lead/Leistung über `POST /one_off_event_types` (liefert eine
 * `scheduling_url`) sowie Absage/Umbuchung über
 * `POST /scheduled_events/{uuid}/cancellation`.
 *
 * Bewusst NICHT abgebildet: das Einspeisen von WorkDiary-Dispositions-Slots als
 * Calendly-Verfügbarkeit — Calendly berechnet Verfügbarkeit aus dem verbundenen
 * Kalender + Event-Type-Regeln (API-Grenze, siehe Feature-Doc).
 */
class CalendlyOutboundService {
    /**
     * Erzeugt einen Einmal-Buchungslink (One-off Event Type) und gibt die
     * `scheduling_url` zurück (null bei Misserfolg / fehlendem Host).
     */
    public function createBookingLink(
        CalendlyConnection $connection,
        string $name,
        int $durationMinutes,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): ?string {
        $hostUri = (string) $connection->calendly_user_uri;
        if ($hostUri === '' || ! $connection->isActive()) {
            return null;
        }

        $start = ($startDate ?? CarbonImmutable::now())->toDateString();
        $end = ($endDate ?? CarbonImmutable::now()->addDays(30))->toDateString();

        $resource = (new CalendlyClient($connection))->createOneOffEventType([
            'name' => $name,
            'host' => $hostUri,
            'duration' => max(1, $durationMinutes),
            'date_setting' => [
                'type' => 'date_range',
                'start_date' => $start,
                'end_date' => $end,
            ],
        ]);

        if ($resource === null) {
            return null;
        }

        return is_string($resource['scheduling_url'] ?? null) ? $resource['scheduling_url'] : null;
    }

    /** Sagt einen Calendly-Termin ab (Cancel-Sync). */
    public function cancel(CalendlyConnection $connection, string $eventUuid, ?string $reason = null): bool {
        if (! $connection->isActive()) {
            return false;
        }

        return (new CalendlyClient($connection))->cancelScheduledEvent($eventUuid, $reason);
    }
}
