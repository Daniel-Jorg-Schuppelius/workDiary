<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Reporting;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Optionaler Bezugszeitraum eines Zielwerts (Monat/Quartal/Jahr). Rein
 * dokumentarisch/zur Einordnung — die Reports werten stets den global
 * gewählten Zeitraum aus; das Feld erklärt, worauf der Zielwert kalibriert ist.
 */
enum ReportTargetPeriod: string implements HasLabel {
    use HasOptions;

    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    public function label(): string {
        return (string) __('reporting.target.period.' . $this->value);
    }
}
