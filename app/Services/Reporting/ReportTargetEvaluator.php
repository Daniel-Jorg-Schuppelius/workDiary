<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetEvaluator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Models\ReportTarget;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Support\Collection;

/**
 * Feature 002 (Zielwerte & Benchmarks): wertet hinterlegte Soll-Werte gegen
 * die von den bestehenden Reports berechneten Ist-Werte aus. Rein additiv —
 * es werden KEINE Kennzahlen neu berechnet, nur Soll/Ist verglichen und ein
 * Ampel-Tone abgeleitet.
 *
 * Auswahl des „passenden" Ziels: spezifischster gültiger Treffer gewinnt
 * (Kunde/Projekt/MA vor Org-Fallback), bei Gleichstand der zuletzt angelegte.
 */
class ReportTargetEvaluator {
    /**
     * Lädt alle zum Stichtag gültigen Ziele einer Kennzahl, gruppiert für die
     * schnelle Auflösung je Scope.
     *
     * @return Collection<int, ReportTarget>
     */
    public function load(ReportTargetMetric $metric, ?CarbonInterface $on = null): Collection {
        $date = $on ?? Carbon::today();

        return ReportTarget::query()
            ->where('metric', $metric->value)
            ->validOn($date)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ermittelt den passenden Zielwert für eine Kennzahl im gegebenen Scope.
     *
     * @param  Collection<int, ReportTarget>  $targets  Vorgeladene Ziele (load()).
     */
    public function resolve(Collection $targets, ReportTargetScope $scope, ?int $scopeId): ?ReportTarget {
        /** @var ReportTarget|null $best */
        $best = null;
        $bestSpecificity = -1;

        foreach ($targets as $t) {
            $matches = match ($t->scope) {
                ReportTargetScope::Org => true,
                default => $t->scope === $scope && $t->scope_id !== null && $t->scope_id === $scopeId,
            };
            if (! $matches) {
                continue;
            }
            $spec = $t->scope->specificity();
            if ($spec > $bestSpecificity) {
                $best = $t;
                $bestSpecificity = $spec;
            }
        }

        return $best;
    }

    /**
     * Vergleicht einen Ist-Wert gegen den (vorab aufgelösten) Zielwert.
     *
     * @return array{
     *   target: float,
     *   actual: float|null,
     *   deviation: float|null,
     *   met: bool|null,
     *   tone: string,
     *   note: string|null
     * }|null  null, wenn kein Ziel hinterlegt ist.
     */
    public function evaluate(ReportTargetMetric $metric, ?ReportTarget $target, ?float $actual): ?array {
        if ($target === null) {
            return null;
        }

        $targetValue = (float) $target->target_value;

        if ($actual === null) {
            return [
                'target' => $targetValue,
                'actual' => null,
                'deviation' => null,
                'met' => null,
                'tone' => 'neutral',
                'note' => $target->note,
            ];
        }

        // Abweichung immer als Ist − Soll. Positiv = Ist liegt über dem Ziel.
        $deviation = round($actual - $targetValue, 2);
        $met = $metric->higherIsBetter() ? $actual >= $targetValue : $actual <= $targetValue;

        return [
            'target' => $targetValue,
            'actual' => round($actual, 2),
            'deviation' => $deviation,
            'met' => $met,
            'tone' => $this->tone($deviation, $met),
            'note' => $target->note,
        ];
    }

    /**
     * Bequemer Direktvergleich (lädt + löst + bewertet in einem Aufruf).
     *
     * @return array{target: float, actual: float|null, deviation: float|null, met: bool|null, tone: string, note: string|null}|null
     */
    public function compare(
        ReportTargetMetric $metric,
        ?float $actual,
        ReportTargetScope $scope = ReportTargetScope::Org,
        ?int $scopeId = null,
        ?CarbonInterface $on = null
    ): ?array {
        $targets = $this->load($metric, $on);
        $target = $this->resolve($targets, $scope, $scopeId);

        return $this->evaluate($metric, $target, $actual);
    }

    /**
     * Ampel-Tone: success bei Erreichung, sonst nach Abstand warning/error.
     * Schwelle für „knapp verfehlt" (warning) ist 10 % des Zielwerts bzw.
     * mindestens 5 absolute Prozentpunkte, damit kleine Ziele nicht sofort rot
     * werden.
     */
    private function tone(float $deviation, bool $met): string {
        if ($met) {
            return 'success';
        }

        $shortfall = abs($deviation);

        return $shortfall <= 5.0 ? 'warning' : 'error';
    }
}
