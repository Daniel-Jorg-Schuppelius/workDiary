<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Invoicing;

use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Platform\User;

/** Rechnungsnachfragen folgen dem Abrechnungsrecht der Rechnung (Feature 166). */
class InvoiceInquiryPolicy {
    public function viewAny(User $user): bool {
        return $user->canManageBilling();
    }

    public function view(User $user, InvoiceInquiry $inquiry): bool {
        return $user->canManageBilling() && (int) $inquiry->organization_id === (int) $user->organization_id;
    }

    public function update(User $user, InvoiceInquiry $inquiry): bool {
        return $this->view($user, $inquiry);
    }
}
