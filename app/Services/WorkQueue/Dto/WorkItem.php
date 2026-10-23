<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\WorkQueue\Dto;

use App\Enums\WorkQueue\WorkItemUrgency;
use Carbon\CarbonImmutable;

/**
 * Ein offener Vorgang im Arbeitsvorrat. `$count` > 1 bündelt einen geteilten
 * Eingang („12 offene Zuordnungsfälle“) statt hunderter Einzelzeilen;
 * `$personal` heißt: dem Nutzer ausdrücklich zugewiesen (nicht über Rolle).
 */
final readonly class WorkItem {
    public function __construct(
        public string $source,
        public string $key,
        public string $title,
        public ?string $url,
        public ?string $detail = null,
        public ?CarbonImmutable $dueAt = null,
        public bool $personal = false,
        public int $count = 1,
    ) {}

    public function urgency(CarbonImmutable $now): WorkItemUrgency {
        return WorkItemUrgency::for($this->dueAt, $now);
    }
}
