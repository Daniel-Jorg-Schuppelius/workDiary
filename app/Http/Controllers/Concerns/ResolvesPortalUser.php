<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesPortalUser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Platform\User;
use Illuminate\Support\Facades\Auth;

trait ResolvesPortalUser {
    /**
     * Angemeldeter Portal-Nutzer (Guard `customer`). Der Provider liefert nur
     * Konten mit `customer_id`; die Prüfung sichert das zusätzlich ab.
     */
    protected function portalUser(): User {
        $user = Auth::guard('customer')->user();
        abort_unless($user instanceof User && $user->customer_id !== null, 403);

        return $user;
    }
}
