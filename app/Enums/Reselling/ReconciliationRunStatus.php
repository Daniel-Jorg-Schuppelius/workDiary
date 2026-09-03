<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

enum ReconciliationRunStatus: string implements HasLabel {
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('reselling.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Queued => 'neutral',
            self::Running => 'info',
            self::Done => 'success',
            self::Failed => 'error',
        };
    }

    public function isFinished(): bool {
        return $this === self::Done || $this === self::Failed;
    }
}
