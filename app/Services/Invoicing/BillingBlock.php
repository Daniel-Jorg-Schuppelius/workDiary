<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Ein abrechenbarer Block: Ergebnis der Taktungs- und Zusammenfassungs-Logik
 * im {@see BillableTimeAggregator}. Bündelt einen oder mehrere Zeiteinträge
 * desselben Projekts (+kind) zu einer Rechnungs-/Voucher-Position.
 */
final class BillingBlock {
    /**
     * @param  list<int>  $entryIds   alle im Block enthaltenen TimeEntry-IDs
     * @param  int  $workedMinutes    tatsächlich erfasste Arbeitsminuten (ohne Lücken)
     * @param  int  $rawMinutes       Arbeitsminuten + überbrückte Lücken (vor Taktung)
     * @param  int  $billedMinutes    auf die Taktung aufgerundete Minuten
     * @param  float  $revenue        Σ der Eintrags-rate (Snapshot-Umsatz der Arbeitszeit)
     */
    public function __construct(
        public readonly ?Project $project,
        public readonly ?TimeEntryKind $kind,
        public readonly array $entryIds,
        public readonly int $primaryEntryId,
        public readonly int $workedMinutes,
        public readonly int $rawMinutes,
        public readonly int $billedMinutes,
        public readonly float $revenue,
        public readonly ?Carbon $firstStart,
        public readonly ?Carbon $lastEnd,
        public readonly ?string $description,
    ) {}

    public function billedHours(): float {
        return round($this->billedMinutes / 60.0, 2);
    }

    /**
     * Effektiver Stundensatz aus der tatsächlich gearbeiteten Zeit (gewichteter
     * Mittelwert). Überbrückte Lücken verwässern den Satz NICHT. Wird mit
     * {@see billedHours()} multipliziert, sodass die Taktung den Rechnungsbetrag
     * erhöht. Liefert null, wenn kein Umsatz/keine Arbeitszeit vorliegt.
     */
    public function hourlyRate(): ?float {
        if ($this->workedMinutes <= 0 || $this->revenue <= 0.0) {
            return null;
        }

        return round($this->revenue / ($this->workedMinutes / 60.0), 2);
    }
}
