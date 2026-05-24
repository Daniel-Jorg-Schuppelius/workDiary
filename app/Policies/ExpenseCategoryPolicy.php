<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{ExpenseCategory, User};
use App\Policies\Concerns\HasAdminBypass;

class ExpenseCategoryPolicy {
    use HasAdminBypass;

    /** Alle eingeloggten Nutzer dürfen die Kategorienliste lesen (z. B. Select). */
    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, ExpenseCategory $category): bool {
        return true;
    }

    /** Verwaltung ausschließlich durch Admin (HasAdminBypass). */
    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, ExpenseCategory $category): bool {
        return false;
    }

    public function delete(User $user, ExpenseCategory $category): bool {
        return $category->expenses()->doesntExist();
    }
}
