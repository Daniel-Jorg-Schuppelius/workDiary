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

use App\Models\Customer\Customer;
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

    /**
     * Kunde des Portalkontos: vorhanden und in der Organisation des Kontos,
     * sonst 403 — eine Antwort für alle Portalflächen (Konsolidierungs-Audit
     * 2026-11-02, kon-13).
     */
    protected function portalCustomer(): Customer {
        $user = $this->portalUser();
        $customer = $user->customer;
        abort_unless($customer instanceof Customer && (int) $customer->organization_id === (int) $user->organization_id, 403);

        return $customer;
    }
}
