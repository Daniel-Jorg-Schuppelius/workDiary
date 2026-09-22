<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anwesenheitsstand je Mitglied und Termin (Feature 159, MVP-844): anwesend,
 * teilweise anwesend, entschuldigt, abwesend. Kein Eintrag bleibt
 * ausdrücklich „offen" — das ist kein Status, sondern die Abwesenheit einer
 * Aussage. Nur anwesend/teilweise bringen nach Bestätigung Trainingszeit.
 */
enum ClubAttendanceStatus: string implements HasLabel {
    use HasOptions;

    case Present = 'present';
    case Partial = 'partial';
    case Excused = 'excused';
    case Absent = 'absent';

    public function label(): string {
        return (string) __('enums.club.attendance-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Present => 'success',
            self::Partial => 'info',
            self::Excused => 'ghost',
            self::Absent => 'error',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Present => 'check_circle',
            self::Partial => 'timelapse',
            self::Excused => 'sick',
            self::Absent => 'cancel',
        };
    }

    /** Zählt als Trainingszeit (erst mit Bestätigung der Liste). */
    public function isCreditable(): bool {
        return $this === self::Present || $this === self::Partial;
    }
}
