<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InteractsWithWorkSchedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\{FlexEligibility, WorkSchedule};
use App\Services\Sickness\ContinuedPaymentService;
use App\Support\Sickness\ContinuedPaymentStatus;
use Carbon\CarbonInterface;

/**
 * Arbeitszeit-Helfer des User-Modells: gültiges Arbeitszeitmodell,
 * Gleitzeit-Berechtigung und Lohnfortzahlungs-Status je Stichtag. Aus dem
 * User-Modell extrahiert (Refactoring Welle 2, B6b) — die Relationen
 * (flexEligibilities, sickLeaves …) bleiben im Modell, Verhalten unverändert.
 */
trait InteractsWithWorkSchedule {
    public function workSchedule(?CarbonInterface $on = null): ?WorkSchedule {
        $on = $on ? $on->copy()->startOfDay() : now()->startOfDay();

        return WorkSchedule::query()
            ->where('user_id', $this->id)
            ->where('valid_from', '<=', $on)
            ->where(function ($q) use ($on) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $on);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Ist der User am angegebenen Stichtag (Default: heute) für die
     * Gleitzeit-Erfassung freigeschaltet? Stützt sich auf
     * {@see FlexEligibility}: jede Lücke zwischen Perioden bedeutet
     * explizit "nicht berechtigt", auch wenn vor- oder nachher Perioden
     * existieren.
     */
    public function isFlexEligible(?CarbonInterface $on = null): bool {
        $on = $on ? $on->copy()->startOfDay() : now()->startOfDay();

        return FlexEligibility::query()
            ->where('user_id', $this->id)
            ->where('valid_from', '<=', $on)
            ->where(function ($q) use ($on): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $on);
            })
            ->exists();
    }

    public function currentSicknessStatus(?CarbonInterface $on = null): ContinuedPaymentStatus {
        /** @var ContinuedPaymentService $svc */
        $svc = app(ContinuedPaymentService::class);

        return $svc->statusFor($this, $on);
    }
}
