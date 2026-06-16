<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\{Qualification, ScheduledShift};
use Illuminate\Support\Collection;

/**
 * Prüft, ob der zugewiesene Mitarbeitende die für den Schichttyp hinterlegten
 * Pflichtqualifikationen besitzt (Feature 013, MVP „Sperrhinweis, wenn
 * Pflichtqualifikation fehlt"). Bewusst gleiche „hält/hält-nicht"-Semantik wie
 * {@see StaffingSuggester::isQualified()} — der Ablauf (Gültigkeit) wird separat
 * über den Deadline-Scanner (`QualificationExpiring`) gewarnt.
 */
final class QualificationGate {
    /**
     * Fehlende Pflichtqualifikationen für die Schicht (leer = alles erfüllt
     * bzw. keine Anforderungen / kein Schichttyp / keine Zuweisung).
     *
     * @return Collection<int, Qualification>
     */
    public function missingFor(ScheduledShift $shift): Collection {
        $shiftType = $shift->shiftType;
        if ($shiftType === null) {
            return collect();
        }

        /** @var Collection<int, Qualification> $required */
        $required = $shiftType->qualifications;
        if ($required->isEmpty()) {
            return collect();
        }

        $user = $shift->user;
        if ($user === null) {
            return collect();
        }

        $heldIds = array_map('intval', $user->qualifications->pluck('id')->all());

        return $required
            ->reject(fn(Qualification $q): bool => in_array((int) $q->id, $heldIds, true))
            ->values();
    }
}
