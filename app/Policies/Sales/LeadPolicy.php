<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Leads (Feature 091) — dieselben Rechte wie der Lead-Controller (Kundenrechte).
 * Die Policy brauchen die Anhänge am Lead (Anhang folgt Träger, Feature 182).
 */
class LeadPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::CustomerViewAny->value);
    }

    public function view(User $user, Lead $lead): bool {
        return $this->sharesOrganization($user, $lead) && $user->can(Permission::CustomerView->value);
    }

    /** Anonymisierte Leads bleiben unveränderlich. */
    public function update(User $user, Lead $lead): bool {
        return $this->sharesOrganization($user, $lead)
            && $user->can(Permission::CustomerUpdate->value)
            && $lead->anonymized_at === null;
    }
}
