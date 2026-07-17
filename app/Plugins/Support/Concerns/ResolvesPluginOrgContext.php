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

use App\Models\{Organization, User};
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsames admin()/organization()-Helferpaar der Plugin-Admin-Controller
 * (Konsolidierung B7): Org-Admin erzwingen (403), Organisationskontext
 * erzwingen (422) — Semantik unverändert aus den 14 wortgleichen Kopien.
 */
trait ResolvesPluginOrgContext {
    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
