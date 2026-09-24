<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Models\Claims\ClaimCase;
use App\Models\Platform\{Organization, User};

/**
 * Reklamationsfall aus einem anderen Modul eröffnen (Prüfabweichung, Miete,
 * Druck) — MVP-863. Gebunden an {@see \App\Services\Claims\ClaimCaseService};
 * die Null-Bindung wirft {@see \App\Modules\ModuleUnavailableException}.
 */
interface ClaimIntake {
    /** @param array<string, mixed> $attributes */
    public function open(Organization $organization, User $creator, array $attributes): ClaimCase;
}
