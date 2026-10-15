<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlannedBreak.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing\Solver;

/** Pflichtpause der Lösung; `beforeJobId` nennt den Stopp, in dessen Service oder vor dessen Beginn sie liegt. */
final class PlannedBreak {
    public function __construct(
        public readonly ?int $beforeJobId,
        public readonly int $start,
        public readonly int $end,
    ) {}

    /** @return array<string, int|null> */
    public function toArray(): array {
        return ['before_job_id' => $this->beforeJobId, 'start' => $this->start, 'end' => $this->end];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        return new self(isset($data['before_job_id']) ? (int) $data['before_job_id'] : null, (int) $data['start'], (int) $data['end']);
    }
}
