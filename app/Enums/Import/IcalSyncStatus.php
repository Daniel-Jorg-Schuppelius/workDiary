<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSyncStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Import;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Ergebnis des letzten Abrufs eines Kalender-Abos (MVP-1516). */
enum IcalSyncStatus: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case NotModified = 'not_modified';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.import.ical_sync_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::NotModified => 'info',
            self::Failed => 'error',
        };
    }
}
