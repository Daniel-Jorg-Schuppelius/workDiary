<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationBalance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Absence;

use Carbon\CarbonInterface;

/** Saldo eines Urlaubskontos (MVP-413) — alle Werte in Arbeitstagen. */
final readonly class VacationBalance {
    public function __construct(
        public int $year,
        public bool $hasEntitlement,
        public float $entitledDays,
        public float $carryoverDays,
        public ?CarbonInterface $carryoverExpiresOn,
        /** Nutzbarer Übertrag: vor Verfall der volle, danach nur der bis dahin verbrauchte Anteil. */
        public float $usableCarryoverDays,
        public float $takenDays,
        public float $pendingDays,
    ) {}

    public function totalDays(): float {
        return $this->entitledDays + $this->usableCarryoverDays;
    }

    /** Rest nach genehmigten Tagen (ohne offene Anträge). */
    public function remainingDays(): float {
        return $this->totalDays() - $this->takenDays;
    }

    /** Rest, wenn zusätzlich alle offenen Anträge genehmigt würden. */
    public function remainingAfterPendingDays(): float {
        return $this->remainingDays() - $this->pendingDays;
    }

    public function wouldOverdraw(float $additionalDays): bool {
        return $this->hasEntitlement && ($this->remainingAfterPendingDays() - $additionalDays) < 0;
    }
}
