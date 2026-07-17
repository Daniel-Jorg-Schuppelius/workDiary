<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimActionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Maßnahmenstatus (MVP-251). */
enum ClaimActionStatus: string implements HasLabel {
    use HasOptions;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Planned => (string) __('Geplant'),
            self::InProgress => (string) __('In Arbeit'),
            self::Done => (string) __('Erledigt'),
            self::Cancelled => (string) __('Abgebrochen'),
        };
    }
}
