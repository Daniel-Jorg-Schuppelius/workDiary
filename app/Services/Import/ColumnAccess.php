<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ColumnAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Platform\User;
use App\Services\Import\Contracts\GuardsColumns;

/**
 * Spaltenrechte der Datenübertragung (MVP-1625): eine Regel für Export,
 * Download, Importvorprüfung und Importlauf. Ohne Person (Systemlauf) gilt
 * keine geschützte Spalte als erlaubt.
 */
final class ColumnAccess {
    /** @return list<string> Spalten der Spec, die die Person nicht lesen bzw. schreiben darf */
    public static function denied(object $spec, ?User $user): array {
        if (! $spec instanceof GuardsColumns) {
            return [];
        }

        $denied = [];
        foreach ($spec->columnPermissions() as $column => $permission) {
            if (! $user instanceof User || ! $user->can($permission->value)) {
                $denied[] = $column;
            }
        }

        return $denied;
    }
}
