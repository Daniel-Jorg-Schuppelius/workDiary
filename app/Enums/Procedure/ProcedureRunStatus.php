<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProcedureRunStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InProgress = 'inProgress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Aborted = 'aborted';

    public function label(): string {
        return (string) __('enums.procedure.run-status.' . $this->value);
    }

    public function isFinal(): bool {
        return match ($this) {
            self::Completed, self::Aborted => true,
            default => false,
        };
    }

    public function isActive(): bool {
        return match ($this) {
            self::Open, self::InProgress, self::Blocked => true,
            default => false,
        };
    }
}
