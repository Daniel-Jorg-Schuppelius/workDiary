<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsUserPeriodMatrix.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Concerns;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matrix Benutzer × Periode der Wochen- und Monatsauswertung. Die Minuten je
 * Periode stehen unter `days` bzw. `months`.
 */
trait BuildsUserPeriodMatrix {
    /**
     * Heatmap-Zeilen (Minuten; Anzeige h:mm über die format-Prop).
     *
     * @param  array<int, array{total: int, rate: float, days?: array<int, int>, months?: array<int, int>}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  'days'|'months'  $periodKey
     * @return list<array{label: string, cells: list<array{value: int}>}>
     */
    private function heatmapRows(array $byUser, Collection $users, string $periodKey): array {
        $rows = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $rows[] = [
                'label' => $userModel instanceof User ? $userModel->name : '#' . $uid,
                'cells' => array_map(fn(int $minutes): array => ['value' => $minutes], array_values($row[$periodKey] ?? [])),
            ];
        }

        return $rows;
    }

    /**
     * Exportzeilen: je Benutzer Name, Minuten je Periode, Summe und Erlös;
     * zuletzt die Gesamtzeile.
     *
     * @param  array<int, array{total: int, rate: float, days?: array<int, int>, months?: array<int, int>}>  $byUser
     * @param  Collection<int, User>  $users
     * @param  'days'|'months'  $periodKey
     * @param  array<int, int>  $periodTotals
     * @return list<list<int|float|string|null>>
     */
    private function buildRows(array $byUser, Collection $users, string $periodKey, array $periodTotals, int $total, float $rate): array {
        $rows = [];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $cols = [(string) $name];
            foreach ($row[$periodKey] ?? [] as $m) {
                $cols[] = (int) $m;
            }
            $cols[] = (int) $row['total'];
            $cols[] = (float) $row['rate'];
            $rows[] = $cols;
        }
        $totalRow = ['Gesamt'];
        foreach ($periodTotals as $m) {
            $totalRow[] = (int) $m;
        }
        $totalRow[] = $total;
        $totalRow[] = $rate;
        $rows[] = $totalRow;

        return $rows;
    }
}
