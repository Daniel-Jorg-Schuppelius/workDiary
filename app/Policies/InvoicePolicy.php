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

use App\Models\{Invoice, User};

class InvoicePolicy {
    public function viewAny(User $user): bool {
        return $user->canManageBilling();
    }

    public function view(User $user, Invoice $invoice): bool {
        return $user->canManageBilling();
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function delete(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function issue(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function pay(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->status === Invoice::STATUS_ISSUED;
    }

    /**
     * Direktes Storno: nur für unbezahlte Rechnungen (draft/issued).
     * Bezahlte Rechnungen müssen über eine Korrekturrechnung
     * (siehe {@see createCreditNote}) storniert werden.
     */
    public function cancel(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->canBeCancelled();
    }

    /**
     * Gutschrift (Korrekturrechnung): nur für bezahlte Original-Rechnungen,
     * und nur genau einmal pro Original (siehe Invoice::needsCreditNoteToCancel()).
     */
    public function createCreditNote(User $user, Invoice $invoice): bool {
        return $user->canManageBilling() && $invoice->needsCreditNoteToCancel();
    }

    /**
     * Mailversand: alle Rechnungen außer Drafts ohne Empfänger-Eingabe.
     * Drafts werden beim Versand automatisch auf "issued" gehoben.
     * Stornierte Rechnungen dürfen nochmal verschickt werden (z.B. zur Info).
     */
    public function send(User $user, Invoice $invoice): bool {
        return $user->canManageBilling();
    }
}
