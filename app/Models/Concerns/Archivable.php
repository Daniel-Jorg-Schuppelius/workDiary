<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Archivable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

/**
 * Soft-Archivierung über die Spalte archived_at (NICHT Laravels SoftDeletes).
 * Setzt eine nullable `archived_at`-Spalte voraus. Bewusst ohne scopeActive(),
 * da dieser Name projektweit bereits mit anderer Bedeutung belegt ist.
 */
trait Archivable {
    public function isArchived(): bool {
        return $this->archived_at !== null;
    }
}
