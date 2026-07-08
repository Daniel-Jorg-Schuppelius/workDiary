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

use App\Models\{Qualification, ScheduledShift, User};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\{Carbon, Collection};

/**
 * Prüft, ob der zugewiesene Mitarbeitende die für den Schichttyp hinterlegten
 * Pflichtqualifikationen besitzt UND ob diese am Schichttag gültig sind
 * (Feature 013). Eine abgelaufene oder noch nicht gültige Qualifikation zählt
 * nicht als „gehalten" — die Schicht wird wie bei fehlender Qualifikation
 * gesperrt (nicht nur über den Deadline-Scanner gewarnt).
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

        // Am Schichttag gültige Qualifikationen zählen als „gehalten". Eine
        // abgelaufene/noch nicht gültige Qualifikation wird ignoriert → die
        // Pflichtqualifikation gilt als fehlend und die Schicht ist gesperrt.
        $shiftDate = $shift->date; // date-Cast → Carbon (Schichttag), sonst null

        $heldIds = [];
        foreach ($user->qualifications as $qualification) {
            if ($this->validOn($qualification, $shiftDate)) {
                $heldIds[] = (int) $qualification->id;
            }
        }

        return $required
            ->reject(fn(Qualification $q): bool => in_array((int) $q->id, $heldIds, true))
            ->values();
    }

    /** Vorwarnfenster der Matrix: „läuft ab", wenn valid_until < Stichtag + 30 Tage. */
    public const EXPIRING_DAYS = 30;

    /**
     * Status je geforderter Qualifikation für einen Mitarbeitenden am
     * Stichtag (Rang 53, Auftrags-Qualifikationsmatrix) — dieselbe
     * Gültigkeitslogik wie {@see missingFor()} (keine Doppellogik):
     * `ok` (gültig, kein baldiger Ablauf), `expiring` (gültig, läuft binnen
     * 30 Tagen ab), `missing` (nicht gehalten / abgelaufen / noch nicht gültig).
     *
     * @param  Collection<int, Qualification>  $required
     * @return array<int, 'ok'|'expiring'|'missing'>  qualification_id → Status
     */
    public function statusFor(User $user, Collection $required, ?CarbonInterface $date): array {
        $held = [];
        foreach ($user->qualifications as $qualification) {
            if (! $this->validOn($qualification, $date)) {
                continue;
            }

            $status = 'ok';
            $pivot = $qualification->getRelationValue('pivot');
            if ($date !== null && $pivot instanceof Pivot) {
                $until = $pivot->getAttribute('valid_until');
                if (is_string($until) && $until !== ''
                    && Carbon::parse($until)->startOfDay()->lt($date->copy()->startOfDay()->addDays(self::EXPIRING_DAYS))) {
                    $status = 'expiring';
                }
            }

            $held[(int) $qualification->id] = $status;
        }

        $result = [];
        foreach ($required as $qualification) {
            $result[(int) $qualification->id] = $held[(int) $qualification->id] ?? 'missing';
        }

        return $result;
    }

    /** Ist die (über die Pivot-Daten befristete) Qualifikation am Stichtag gültig? */
    private function validOn(Qualification $qualification, ?CarbonInterface $date): bool {
        if ($date === null) {
            return true; // ohne Schichttag keine Befristungsprüfung
        }

        $pivot = $qualification->getRelationValue('pivot');
        if (! $pivot instanceof Pivot) {
            return true; // kein Pivot geladen → keine Befristung
        }

        $day = $date->copy()->startOfDay();

        $from = $pivot->getAttribute('valid_from');
        if (is_string($from) && $from !== '' && Carbon::parse($from)->startOfDay()->gt($day)) {
            return false; // noch nicht gültig
        }

        $until = $pivot->getAttribute('valid_until');
        if (is_string($until) && $until !== '' && Carbon::parse($until)->startOfDay()->lt($day)) {
            return false; // abgelaufen
        }

        return true;
    }
}
