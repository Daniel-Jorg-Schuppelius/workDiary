<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeApproval;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Überstunden-Antrags (MVP-519). Bewusst ohne Draft-Stufe —
 * ein Überstunden-Antrag ist mit dem Absenden eingereicht.
 */
enum OvertimeRequestStatus: string implements HasLabel {
    use HasOptions;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return match ($this) {
            self::Submitted => __('Eingereicht'),
            self::Approved  => __('Genehmigt'),
            self::Rejected  => __('Abgelehnt'),
            self::Withdrawn => __('Zurückgezogen'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved  => 'success',
            self::Rejected  => 'error',
            self::Withdrawn => 'ghost',
        };
    }

    public function isTerminal(): bool {
        return $this !== self::Submitted;
    }
}
