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
     * @param  int  $rawMinutes       Arbeitsminuten + überbrückte Lücken + Anfahrt (vor Taktung)
     * @param  int  $billedMinutes    auf die Taktung aufgerundete Minuten
     * @param  float  $revenue        Σ der Eintrags-rate (Snapshot-Umsatz inkl. pauschaler Anfahrt)
     * @param  int  $travelMinutes    pauschale Anfahrt der Kundenkondition (steckt bereits im Umsatz)
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
        public readonly int $travelMinutes = 0,
    ) {}

    public function billedHours(): float {
        // 3 NK (statt 2): die Mengen-Quantisierung war die Hauptursache der Abweichung Rechnungsbetrag ↔
        // TimeEntry-Snapshot (50 min @ 85 €/h: 0,83 h × 85 = 70,55 statt 70,83). Ein Cent-Rest bleibt möglich.
        return round($this->billedMinutes / 60.0, 3);
    }

    /**
     * Effektiver Stundensatz (gewichteter Mittelwert). Überbrückte Lücken und
     * die Taktungs-Aufrundung verwässern den Satz NICHT — sie erhöhen über
     * {@see billedHours()} bewusst den Rechnungsbetrag.
     *
     * Die pauschale Anfahrt (Feature 098) zählt dagegen mit: ihr Geld steckt
     * bereits im `rate`-Snapshot UND ihre Minuten in der Menge. Ohne sie im
     * Nenner käme sie doppelt an (60 Min + 20 Min Anfahrt @ 90 €/h ⇒ 1,33 h ×
     * 120 € = 160 € statt der abgerechneten 120 €).
     *
     * Liefert null, wenn kein Umsatz/keine Zeit vorliegt.
     */
    public function hourlyRate(): ?float {
        $divisorMinutes = $this->workedMinutes + $this->travelMinutes;
        if ($divisorMinutes <= 0 || $this->revenue <= 0.0) {
            return null;
        }

        return round($this->revenue / ($divisorMinutes / 60.0), 2);
    }

    /**
     * Anzeigename der Position (Vollaudit 2026-07, M45): Projektname + [kind]
     * + Zeitraum-Span — vorher 4× in den Facturation-Targets kopiert (die
     * Lexoffice-Variante mit Beschreibungs-Präfix bei Einzeleintrag bleibt
     * als $withDescription-Flag erhalten; die übrigen Ziele behalten bewusst
     * ihr bisheriges Verhalten ohne Präfix — fachliche Vereinheitlichung wäre
     * eine eigene Entscheidung).
     */
    public function displayName(\App\Models\Finance\BillingTransfer $transfer, bool $withDescription = false): string {
        $projectName = $this->project?->name ?: (string) __('Leistung');
        $kindSuffix = $this->kind !== null ? ' [' . $this->kind->value . ']' : '';

        $from = $this->firstStart?->format('d.m.Y') ?? $transfer->period_from?->format('d.m.Y');
        $to = $this->lastEnd?->format('d.m.Y') ?? $transfer->period_to?->format('d.m.Y');
        $span = match (true) {
            $from !== null && $to !== null && $from !== $to => sprintf(' (%s – %s)', $from, $to),
            $from !== null => sprintf(' (%s)', $from),
            default => '',
        };

        $name = trim($projectName . $kindSuffix . $span);
        if ($withDescription && count($this->entryIds) === 1 && $this->description !== null) {
            $name = trim($this->description) . ' · ' . $name;
        }

        return $name;
    }
}
