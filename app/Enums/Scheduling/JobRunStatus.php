<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Scheduling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis eines Scheduler-Laufs (Feature 067, MVP-177) — für den einzelnen
 * Lauf (scheduled_job_runs.status) wie für den letzten Stand je Job
 * (scheduled_job_states.last_status). skipped = Überlappungssperre oder
 * Wartungsfenster.
 */
enum JobRunStatus: string implements HasLabel {
    use HasOptions;

    case Success = 'success';
    case Failed = 'failed';
    case Running = 'running';
    case Skipped = 'skipped';

    public function label(): string {
        return __('scheduler.state.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Success => 'success',
            self::Failed => 'error',
            self::Running => 'info',
            self::Skipped => 'neutral',
        };
    }
}
