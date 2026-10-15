<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnassignedJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Nicht einplanbarer Auftrag mit Grund. */
final class UnassignedJob {
    public const REASON_NO_QUALIFICATION = 'no_qualification';

    public const REASON_TIME_WINDOW = 'time_window';

    public const REASON_DAY_FULL = 'day_full';

    public const REASON_UNREACHABLE = 'unreachable';

    public const REASON_NO_COORDINATES = 'no_coordinates';

    public function __construct(
        public readonly int $jobId,
        public readonly string $reason,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array {
        return ['job_id' => $this->jobId, 'reason' => $this->reason];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self((int) $data['job_id'], (string) $data['reason']);
    }
}
