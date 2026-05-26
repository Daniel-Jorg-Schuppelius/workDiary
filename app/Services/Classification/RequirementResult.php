<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\ClassificationRequirementSeverity;

class RequirementResult {
    public function __construct(
        public readonly int $requirementId,
        public readonly string $requiredDomain,
        public readonly ClassificationRequirementSeverity $severity,
        public readonly int $actualCount,
        public readonly int $minCount,
        public readonly ?int $maxCount,
        public readonly string $phase,
    ) {}

    public function isBlocking(): bool {
        return $this->severity->isHard();
    }
}
