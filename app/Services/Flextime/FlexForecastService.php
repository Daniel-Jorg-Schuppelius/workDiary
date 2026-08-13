<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexForecastService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Flextime;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{FlexBalance, ScheduledShift, User};
use Carbon\CarbonImmutable;

/**
 * Vorausberechnung des Gleitzeitsaldos (MVP-521, Q1-Konzept
 * „Vorausberechnung auf Basis geplanter Dienste"): projiziert den
 * kumulierten Saldo monatsweise in die Zukunft.
 *
 * Monate MIT geplanten Diensten rechnen geplante Dienstminuten gegen das
 * Sollmodell; Monate OHNE Dienste unterstellen Solltreue (Δ 0) — für reine
 * Gleitzeit-Organisationen bleibt die Projektion damit konstant, für
 * Schichtbetriebe zeigt sie die Wirkung der Dienstplanung auf die Konten.
 */
final class FlexForecastService {
    public function __construct(
        private readonly FlexCalculator $calculator,
        private readonly FlexTrafficLight $trafficLight,
    ) {}

    /**
     * @return array{start_balance: int, months: list<array{key: string, label: string, target: int, planned: int, has_shifts: bool, delta: int, projected: int, tone: string}>}
     */
    public function forecast(User $user, int $months = 6, ?CarbonImmutable $from = null): array {
        $months = max(1, min(12, $months));
        $from ??= CarbonImmutable::now();

        // Kumulierter Ausgangssaldo: jüngste FlexBalance-Zeile (gleiche
        // Lesart wie Urlaub-&-Flex-Auswertung und Terminal-Statusantwort).
        $latest = FlexBalance::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first(['balance_minutes']);
        $running = $latest !== null ? (int) $latest->balance_minutes : 0;
        $startBalance = $running;

        $rows = [];
        $cursor = $from->addMonth()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            $target = 0;
            for ($day = $monthStart; $day->lessThanOrEqualTo($monthEnd); $day = $day->addDay()) {
                $target += $this->calculator->targetMinutes($user, $day);
            }

            [$planned, $hasShifts] = $this->plannedMinutes($user, $monthStart, $monthEnd);

            $delta = $hasShifts ? $planned - $target : 0;
            $running += $delta;

            $rows[] = [
                'key' => $monthStart->format('Y-m'),
                'label' => $monthStart->translatedFormat('M Y'),
                'target' => $target,
                'planned' => $planned,
                'has_shifts' => $hasShifts,
                'delta' => $delta,
                'projected' => $running,
                'tone' => $this->trafficLight->tone($running),
            ];

            $cursor = $cursor->addMonth();
        }

        return ['start_balance' => $startBalance, 'months' => $rows];
    }

    /**
     * Geplante Dienstminuten im Zeitraum (stornierte Dienste zählen nicht;
     * Nachtdienste über Mitternacht werden als +24 h interpretiert).
     *
     * @return array{int, bool} [Minuten, Dienste vorhanden?]
     */
    private function plannedMinutes(User $user, CarbonImmutable $from, CarbonImmutable $to): array {
        $shifts = ScheduledShift::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->get(['date', 'start_time', 'end_time']);

        if ($shifts->isEmpty()) {
            return [0, false];
        }

        $minutes = 0;
        foreach ($shifts as $shift) {
            if ($shift->start_time === null || $shift->end_time === null) {
                continue;
            }
            $start = $this->timeToMinutes((string) $shift->start_time);
            $end = $this->timeToMinutes((string) $shift->end_time);
            $minutes += $end > $start ? $end - $start : (24 * 60 - $start) + $end;
        }

        return [$minutes, true];
    }

    private function timeToMinutes(string $time): int {
        [$h, $m] = array_map(intval(...), array_pad(explode(':', $time), 2, '0'));

        return $h * 60 + $m;
    }
}
