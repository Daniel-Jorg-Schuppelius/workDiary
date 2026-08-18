<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HoaiCostReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Models\Costing\CostEstimate;
use App\Models\Project;

/**
 * Kostenermittlung nach den vier HOAI-Stufen als Bericht (Feature 109,
 * MVP-644).
 *
 * **Der Bericht stellt die Stufen nebeneinander, er ersetzt sie nicht.**
 * Kostenschätzung, -berechnung, -anschlag und -feststellung lösen einander
 * nicht ab; ihr Vergleich *ist* die Kostenkontrolle. Deshalb steht je
 * Kostengruppe eine Zeile mit allen vier Spalten und der Abweichung zwischen
 * der ersten und der letzten vorhandenen Stufe.
 *
 * **Fehlt eine Stufe, bleibt die Spalte leer.** Sie mit der Nachbarstufe zu
 * füllen erzeugte den Eindruck einer Ermittlung, die niemand angestellt hat.
 */
final class HoaiCostReportService {
    /**
     * @return array{
     *     stages: array<string, CostEstimate|null>,
     *     rows: list<array{code: string, label: string, amounts: array<string, float|null>, delta: float|null}>,
     *     totals: array<string, float|null>,
     *     delta: float|null
     * }
     */
    public function forProject(Project $project): array {
        $stages = [];
        foreach (CostEstimate::STAGES as $stage) {
            // Je Stufe die jüngste: Eine spätere Fassung ersetzt die frühere
            // derselben Stufe - aber nie eine andere Stufe.
            $stages[$stage] = CostEstimate::query()
                ->where('project_id', $project->id)
                ->where('stage', $stage)
                ->orderByDesc('determined_on')
                ->orderByDesc('id')
                ->first();
        }

        $sums = [];
        $labels = [];
        foreach ($stages as $stage => $estimate) {
            if ($estimate === null) {
                continue;
            }
            foreach ($this->amountsOf($estimate) as $code => $amount) {
                $sums[$code][$stage] = ($sums[$code][$stage] ?? 0.0) + $amount;
                $labels[$code] ??= null;
            }
            foreach ($estimate->items as $item) {
                $code = $this->codeOf($item->code);
                $labels[$code] = $labels[$code] ?? $item->label;
            }
        }

        ksort($sums);

        $rows = [];
        $totals = array_fill_keys(CostEstimate::STAGES, null);
        foreach ($sums as $code => $perStage) {
            $amounts = [];
            foreach (CostEstimate::STAGES as $stage) {
                $value = isset($perStage[$stage]) ? round($perStage[$stage], 2) : null;
                $amounts[$stage] = $value;
                if ($value !== null) {
                    $totals[$stage] = round(($totals[$stage] ?? 0.0) + $value, 2);
                }
            }

            $rows[] = [
                'code' => (string) $code,
                'label' => $labels[$code] ?? (string) __('Ohne Zuordnung'),
                'amounts' => $amounts,
                'delta' => $this->deltaOf($amounts),
            ];
        }

        return [
            'stages' => $stages,
            'rows' => $rows,
            'totals' => $totals,
            'delta' => $this->deltaOf($totals),
        ];
    }

    /**
     * Beträge einer Ermittlung je Kostengruppe.
     *
     * Gezählt werden **nur Blattelemente** — eine Ermittlung führt Ober- und
     * Untergruppen nebeneinander, und beide zu summieren zählte dasselbe Geld
     * zweimal.
     *
     * @return array<string, float>
     */
    private function amountsOf(CostEstimate $estimate): array {
        $items = $estimate->items;
        $parents = $items->pluck('parent_code')->filter()->unique()->all();

        $amounts = [];
        foreach ($items as $item) {
            if ($item->amount === null) {
                continue;
            }
            $code = trim((string) $item->code);
            if ($code !== '' && in_array($code, $parents, true)) {
                continue;
            }
            // Auf die erste Ebene zusammenfassen: Der Stufenvergleich ist eine
            // Übersicht, keine Positionsliste.
            $key = $this->codeOf($item->code);
            $amounts[$key] = ($amounts[$key] ?? 0.0) + (float) $item->amount;
        }

        return $amounts;
    }

    /** „311" wird zu „300" — der Bericht vergleicht auf der ersten Ebene. */
    private function codeOf(?string $code): string {
        $digits = preg_replace('/\D/', '', (string) $code) ?? '';

        return $digits === '' ? '' : substr($digits, 0, 1) . str_repeat('0', max(0, strlen($digits) - 1));
    }

    /**
     * Abweichung zwischen der **ersten und der letzten vorhandenen** Stufe.
     *
     * Mit weniger als zwei Stufen gibt es nichts zu vergleichen — dann bleibt
     * es `null` statt 0, denn null hieße „keine Abweichung".
     *
     * @param array<string, float|null> $amounts
     */
    private function deltaOf(array $amounts): ?float {
        $present = array_filter($amounts, static fn (?float $value): bool => $value !== null);
        if (count($present) < 2) {
            return null;
        }

        $values = array_values($present);

        return round(end($values) - $values[0], 2);
    }
}
