<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoicePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\ExternalInvoice;
use App\Models\Platform\User;

/**
 * Extern ausgestellte Rechnungen (Feature 196): sehen wie Übergabenachweise,
 * zuordnen und Nachweise pflegen mit einem Übergaberecht, zustellen wie eine
 * lokale Rechnung (Abrechnungsrecht, {@see \App\Policies\Invoicing\InvoicePolicy::send()}).
 */
class ExternalInvoicePolicy {
    public function view(User $user, ExternalInvoice $invoice): bool {
        return $user->can(P::FinanceViewAny->value);
    }

    public function update(User $user, ExternalInvoice $invoice): bool {
        return $user->canManageBilling()
            || $user->can(P::FinanceTransferTime->value)
            || $user->can(P::FinanceTransferMaterial->value);
    }

    public function send(User $user, ExternalInvoice $invoice): bool {
        return $user->canManageBilling();
    }
}
