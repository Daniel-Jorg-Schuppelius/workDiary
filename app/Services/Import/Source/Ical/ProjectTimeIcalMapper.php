<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeIcalMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source\Ical;

use App\Services\Import\Source\IcalEventMapper;

/**
 * iCal → Projektzeit-Zeile (MVP-438).
 *
 * `SUMMARY` wird zum Projekt-Namen (Matcher, Inbox-First bei Nicht-Treffer),
 * `DESCRIPTION` zur Beschreibung. Projektzeiten sind keine Anwesenheitswahrheit
 * → `TRANSP:TRANSPARENT` wird **nicht** übersprungen.
 */
final class ProjectTimeIcalMapper implements IcalEventMapper {
    public function toRow(IcalEvent $event): array {
        return [
            'user_email' => $event->email ?? '',
            'date' => $event->date ?? '',
            'start_time' => $event->startTime ?? '',
            'end_time' => $event->endTime ?? '',
            'project' => $event->summary,
            'description' => $event->description,
            'external_id' => $event->uid,
        ];
    }

    public function skipsTransparent(): bool {
        return false;
    }
}
