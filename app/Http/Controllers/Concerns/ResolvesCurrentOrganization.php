<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesCurrentOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;

/**
 * Liefert die für den Request gebundene Organisation (Single Source of Truth:
 * das von SetOrganizationContext gebundene Modell) oder bricht mit 403 ab.
 *
 * Bewusst die STRIKTE Variante (non-nullable, kein User-Fallback): für
 * Admin-/Org-gebundene Controller, die ohne gültigen Org-Kontext gar nicht
 * arbeiten dürfen. Controller mit weicherem Fallback (nullable bzw.
 * `currentOrganizationId(): int`) nutzen diesen Trait absichtlich NICHT.
 */
trait ResolvesCurrentOrganization {
    protected function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);

        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
