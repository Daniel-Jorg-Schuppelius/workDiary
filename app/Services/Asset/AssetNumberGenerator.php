<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetNumberGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Numbering\NumberScope;
use App\Services\Numbering\NumberSequenceService;
use Carbon\CarbonImmutable;

class AssetNumberGenerator {
    public function __construct(
        private readonly NumberSequenceService $numberSequence,
    ) {}

    public function generate(int $organizationId, ?CarbonImmutable $now = null): string {
        return $this->numberSequence->next($organizationId, NumberScope::Asset, $now ?? CarbonImmutable::now());
    }
}
