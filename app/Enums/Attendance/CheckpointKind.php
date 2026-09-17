<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckpointKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Wo ein Check-in-Punkt (QR-Code oder NFC-Aufkleber) hängt (MVP-800). */
enum CheckpointKind: string implements HasLabel {
    use HasOptions;

    case Site = 'site';
    case Vehicle = 'vehicle';

    public function label(): string {
        return (string) __('attendance.checkpoint_kind.' . $this->value);
    }
}
