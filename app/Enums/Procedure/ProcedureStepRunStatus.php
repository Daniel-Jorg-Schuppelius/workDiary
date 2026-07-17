<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProcedureStepRunStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Done = 'done';
    case NA = 'n_a';
    case Failed = 'failed';
    case Deviated = 'deviated';
    case Blocked = 'blocked';

    public function label(): string {
        return (string) __('enums.procedure.step-run-status.' . $this->value);
    }

    public function isFinal(): bool {
        return match ($this) {
            self::Done, self::NA, self::Failed, self::Deviated => true,
            default => false,
        };
    }

    public function isOpen(): bool {
        return ! $this->isFinal();
    }
}
