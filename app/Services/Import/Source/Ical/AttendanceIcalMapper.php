<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceIcalMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source\Ical;

use App\Services\Import\Source\IcalEventMapper;

/**
 * iCal → Stempelungs-Zeile (MVP-438).
 *
 * Datenschutz-Vorgabe (Feature 094): aus dem Kalendereintrag wird für die
 * Anwesenheit nur Zeit/Person übernommen; der Titel landet ausschließlich als
 * optionale Notiz. `TRANSP:TRANSPARENT` (frei/OOF) gilt nicht als Anwesenheit.
 */
final class AttendanceIcalMapper implements IcalEventMapper {
    public function toRow(IcalEvent $event): array {
        return [
            'user_email' => $event->email ?? '',
            'date' => $event->date ?? '',
            'start_time' => $event->startTime ?? '',
            'end_time' => $event->endTime ?? '',
            'break_minutes' => '',
            'note' => $event->summary,
            'external_id' => $event->uid,
        ];
    }

    public function skipsTransparent(): bool {
        return true;
    }
}
