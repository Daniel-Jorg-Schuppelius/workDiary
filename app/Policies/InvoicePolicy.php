<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBilling();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canManageBilling();
    }

    public function create(User $user): bool
    {
        return $user->canManageBilling();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_ISSUED;
    }
}
