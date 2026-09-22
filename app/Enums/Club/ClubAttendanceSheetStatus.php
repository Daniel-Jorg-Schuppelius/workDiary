<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceSheetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zustand einer Anwesenheitsliste (MVP-844): offen (Entwurf) oder bestätigt (zählt). */
enum ClubAttendanceSheetStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Confirmed = 'confirmed';

    public function label(): string {
        return (string) __('enums.club.attendance-sheet-status.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Confirmed ? 'success' : 'warning';
    }
}
