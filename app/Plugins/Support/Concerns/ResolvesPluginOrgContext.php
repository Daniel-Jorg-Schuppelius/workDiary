<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesPluginOrgContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Concerns;

use App\Http\Controllers\Concerns\AuthorizesOrganizationManagement;
use App\Models\Platform\{Organization, User};

/**
 * Gemeinsames admin()/organization()-Helferpaar der Plugin-Admin-Controller
 * (Konsolidierung B7): Org-Admin erzwingen (403), Organisationskontext
 * erzwingen (422) — Semantik unverändert aus den 14 wortgleichen Kopien.
 */
trait ResolvesPluginOrgContext {
    use AuthorizesOrganizationManagement;

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
