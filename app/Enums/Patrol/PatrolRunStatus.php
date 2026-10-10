<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Patrol;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Rundgangs (Feature 089): läuft bis zum Abschluss oder Abbruch. */
enum PatrolRunStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Running = 'running';
    case Completed = 'completed';
    case Aborted = 'aborted';

    public function label(): string {
        return match ($this) {
            self::Running => (string) __('enums.patrol.patrol_run_status.running'),
            self::Completed => (string) __('enums.patrol.patrol_run_status.completed'),
            self::Aborted => (string) __('enums.patrol.patrol_run_status.aborted'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Running => [self::Completed, self::Aborted],
            self::Completed, self::Aborted => [],
        };
    }
}
