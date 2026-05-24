<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApproverResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Models\{Expense, User};
use Illuminate\Support\Collection;

/**
 * Bestimmt, welche Nutzer eine eingereichte {@see Expense} entscheiden
 * dürfen. Aktuell: alle Administratoren der zugehörigen Organisation
 * (per {@see User::isAdmin()}); fällt auf alle Admins zurück, wenn die
 * Spese keiner Organisation zugeordnet ist.
 */
class ApproverResolver {
    /**
     * @return Collection<int, User>
     */
    public function approversFor(Expense $expense): Collection {
        $query = User::query();

        if ($expense->organization_id !== null) {
            $query->where('organization_id', $expense->organization_id);
        }

        // Eigentümer nie selbst benachrichtigen.
        $query->where('id', '!=', $expense->user_id);

        return $query->get()
            ->filter(fn(User $user): bool => $user->isAdmin())
            ->values();
    }
}
