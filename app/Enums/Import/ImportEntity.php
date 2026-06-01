<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportEntity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Import;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vom MVP-049 CSV-Import unterstützte Entitäten.
 */
enum ImportEntity: string implements HasLabel {
    use HasOptions;

    case Customers = 'customers';
    case Projects = 'projects';
    case Users = 'users';
    case Materials = 'materials';
    case ScheduledShifts = 'scheduled_shifts';
    case RemoteSessions = 'remote_sessions';

    public function label(): string {
        return (string) __('import.entity.' . $this->value);
    }

    public function permission(): string {
        return match ($this) {
            self::Customers => 'customer.import',
            self::Projects => 'project.import',
            self::Users => 'user.import',
            self::Materials => 'material.import',
            self::ScheduledShifts => 'schedule.import',
            self::RemoteSessions => 'remote-session.import',
        };
    }
}
