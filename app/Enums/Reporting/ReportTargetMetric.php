<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetMetric.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Reporting;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Feature 002 (Zielwerte & Benchmarks): Katalog der Kennzahlen, für die ein
 * Zielwert hinterlegt und im jeweiligen Report gegen den Ist-Wert geprüft
 * werden kann. Es werden bewusst NUR Kennzahlen aufgenommen, die ein
 * bestehender Report bereits berechnet — KEINE neue Kennzahlen-Engine.
 *
 * Jede Kennzahl trägt ihre Maßeinheit ({@see format()}) und die Richtung
 * ({@see higherIsBetter()}), aus der der Evaluator Abweichung und Ampel-Tone
 * ableitet.
 */
enum ReportTargetMetric: string implements HasLabel {
    use HasOptions;

    /** Deckungsbeitrags-Marge in % (EconomicsReportBuilder.margin). Höher = besser. */
    case ContributionMargin = 'contributionMargin';

    /** Abrechenbare Quote in % (1 − Nicht-abrechenbar-Anteil). Höher = besser. */
    case BillableRate = 'billableRate';

    /** Nicht-abrechenbar-/Nacharbeits-Anteil in % (Economics nonBillableShare). Niedriger = besser. */
    case ReworkShare = 'reworkShare';

    /** SLA-Einhaltungsquote in % (SlaReport compliance_rate). Höher = besser. */
    case SlaComplianceRate = 'slaComplianceRate';

    /** Auslastung in % (erfasste Zeit / Soll-Zeit, WorkBalance). Höher = besser. */
    case Utilization = 'utilization';

    public function label(): string {
        return (string) __('reporting.target.metric.' . $this->value);
    }

    /** Maßeinheit für Formatierung/Eingabe: percent | currency | minutes. */
    public function format(): string {
        return match ($this) {
            self::ContributionMargin,
            self::BillableRate,
            self::ReworkShare,
            self::SlaComplianceRate,
            self::Utilization => 'percent',
        };
    }

    /**
     * Richtung: true → Ist sollte das Ziel erreichen/übertreffen (z. B. Marge),
     * false → Ist sollte das Ziel unterschreiten (z. B. Nacharbeitsanteil).
     */
    public function higherIsBetter(): bool {
        return match ($this) {
            self::ReworkShare => false,
            default => true,
        };
    }
}
