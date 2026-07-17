<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimFinancialStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Freigabe-/Übergabestatus der kaufmännischen Folge (MVP-252). */
enum ClaimFinancialStatus: string implements HasLabel {
    use HasOptions;

    case Proposed = 'proposed';
    case Approved = 'approved';
    case Executed = 'executed';
    case Rejected = 'rejected';

    public function label(): string {
        return match ($this) {
            self::Proposed => (string) __('Vorgeschlagen'),
            self::Approved => (string) __('Freigegeben'),
            self::Executed => (string) __('Ausgeführt/übergeben'),
            self::Rejected => (string) __('Abgelehnt'),
        };
    }
}
