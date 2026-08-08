<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPayoutReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\{CompensationModel, FlatInterval, Permission};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters;
use App\Models\{TimeEntry, User};
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Auszahlungs-Report für externe Mitarbeiter (compensation_model != payroll).
 *
 * Für den gewählten Zeitraum:
 *  - pauschal/monatlich:  Festbetrag × Anzahl Monate im Zeitraum
 *  - pauschal/einmalig:   Festbetrag (einmalig)
 *  - pauschal/pro_einsatz: Festbetrag × Anzahl Einsatztage (Tage mit Zeiteinträgen)
 *  - nach_zeitaufwand:    Σ erfasste Zeit × compensation_rate
 *
 * Nur für Payroll-Berechtigte (Permission::UserPayrollManage) – sensible
 * Vergütungsdaten.
 */
class ExternalPayoutReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;

    public function index(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($auth->organization_id !== null && $auth->can(Permission::UserPayrollManage->value), 403);

        [$from, $to] = $this->resolveRange($request);
        $monthCount = max(1, count($this->buildMonthsInRange($from, $to)));

        $filters = $this->standardFilters($request, ['user'], $from, $to);

        // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne
        // expliziten Org-Filter erschienen externe Mitarbeiter (inkl.
        // Vergütungsdaten!) ALLER Organisationen (Tenant-Leak, Bauturbo A17).
        /** @var \Illuminate\Support\Collection<int, User> $externals */
        $externals = User::query()
            ->where('organization_id', $auth->organization_id)
            ->whereIn('compensation_model', [
                CompensationModel::Pauschal->value,
                CompensationModel::NachZeitaufwand->value,
            ])
            ->when($filters->userId !== null, fn($q) => $q->whereKey($filters->userId))
            ->orderBy('name')
            ->get();

        $rows = [];
        $total = 0.0;

        foreach ($externals as $user) {
            $model = $user->compensation_model;
            $minutes = 0;
            $einsatzDays = 0;
            $amount = 0.0;
            $basis = '';

            if ($model === CompensationModel::NachZeitaufwand) {
                $minutes = (int) TimeEntry::query()
                    ->where('user_id', $user->id)
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->sum('minutes');
                $rate = ($user->compensation_rate?->toFloat() ?? 0.0);
                // Auf 2 Dezimalstellen runden (Geldbetrag), damit die Summe der
                // Zeilen mit dem ausgewiesenen Gesamtbetrag übereinstimmt und
                // nicht durch akkumulierte Nachkommastellen abweicht.
                $amount = round($minutes / 60 * $rate, 2);
                $basis = __(':hours × :rate', [
                    'hours' => NumberHelper::toGermanFormat($minutes / 60, 2, withThousandsSeparator: true) . ' h',
                    'rate' => NumberHelper::toGermanFormat($rate, 2, withThousandsSeparator: true) . ' €',
                ]);
            } elseif ($model === CompensationModel::Pauschal) {
                $flat = ($user->flat_amount?->toFloat() ?? 0.0);
                $interval = $user->flat_interval;
                if ($interval === FlatInterval::Monatlich) {
                    $amount = $flat * $monthCount;
                    $basis = NumberHelper::toGermanFormat($flat, 2, withThousandsSeparator: true) . ' € × ' . $monthCount . ' ' . __('Monate');
                } elseif ($interval === FlatInterval::ProEinsatz) {
                    $einsatzDays = TimeEntry::query()
                        ->where('user_id', $user->id)
                        ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                        ->distinct()
                        ->count('date');
                    $amount = $flat * $einsatzDays;
                    $basis = NumberHelper::toGermanFormat($flat, 2, withThousandsSeparator: true) . ' € × ' . $einsatzDays . ' ' . __('Einsätze');
                } else { // Einmalig
                    $amount = $flat;
                    $basis = __('Einmalig');
                }
            }

            $total += $amount;
            $rows[] = [
                'user' => $user,
                'model' => $model,
                'minutes' => $minutes,
                'basis' => $basis,
                'amount' => $amount,
            ];
        }

        return view('reports.external-payouts', [
            'rows' => $rows,
            'total' => $total,
            'from' => $from,
            'to' => $to,
            'monthCount' => $monthCount,
            'standardFilters' => $filters,
            'filterFields' => ['user'],
            'monthlyPayoutSeries' => $this->monthlyPayoutSeries($externals, $from, $to),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
            'payoutByUserSeries' => $this->payoutByUserSeries($rows),
            ...$this->standardFilterOptions(['user'], $filters),
        ]);
    }

    /**
     * Auszahlungen (€) je Bucket (adaptiv zur Header-Granularität):
     * zeitbasierte Vergütung nach Zeitraum der Zeiteinträge, Monatspauschalen
     * je Zeitraum-Bucket, Einsatzpauschalen nach Einsatztagen, Einmalpauschalen
     * im ersten Bucket. Leere Serie statt Null-Achse (§Diagramm-UX).
     *
     * @param  \Illuminate\Support\Collection<int, User>  $externals
     * @return list<array{x: string, y: float}>
     */
    private function monthlyPayoutSeries($externals, CarbonImmutable $from, CarbonImmutable $to): array {
        $granularity = $this->bucketGranularity($from, $to);
        $buckets = $this->buildBucketsInRange($from, $to);
        if ($externals->isEmpty() || $buckets === []) {
            return [];
        }

        // Eine Abfrage für alle zeitabhängigen Modelle: Minuten + Einsatztage
        // je Mitarbeiter × Monat.
        $timeUserIds = $externals
            ->filter(fn(User $u): bool => $u->compensation_model === CompensationModel::NachZeitaufwand
                || ($u->compensation_model === CompensationModel::Pauschal && $u->flat_interval === FlatInterval::ProEinsatz))
            ->pluck('id')
            ->all();

        /** @var array<int, array<string, int>> $minutesByUserMonth */
        $minutesByUserMonth = [];
        /** @var array<int, array<string, array<string, bool>>> $daysByUserMonth */
        $daysByUserMonth = [];
        if ($timeUserIds !== []) {
            $entries = TimeEntry::query()
                ->whereIn('user_id', $timeUserIds)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get(['user_id', 'date', 'minutes']);
            foreach ($entries as $entry) {
                $uid = (int) $entry->user_id;
                $date = CarbonImmutable::parse((string) $entry->date);
                $ym = ChartBucket::keyLabel($granularity, $date)[0];
                $minutesByUserMonth[$uid][$ym] = ($minutesByUserMonth[$uid][$ym] ?? 0) + (int) $entry->minutes;
                $daysByUserMonth[$uid][$ym][$date->toDateString()] = true;
            }
        }

        /** @var array<string, float> $byMonth */
        $byMonth = array_fill_keys(array_column($buckets, 'key'), 0.0);
        $firstMonth = (string) $buckets[0]['key'];

        foreach ($externals as $user) {
            $uid = (int) $user->id;
            $model = $user->compensation_model;
            if ($model === CompensationModel::NachZeitaufwand) {
                $rate = ($user->compensation_rate?->toFloat() ?? 0.0);
                foreach ($minutesByUserMonth[$uid] ?? [] as $ym => $minutes) {
                    if (array_key_exists($ym, $byMonth)) {
                        $byMonth[$ym] += round($minutes / 60 * $rate, 2);
                    }
                }
            } elseif ($model === CompensationModel::Pauschal) {
                $flat = ($user->flat_amount?->toFloat() ?? 0.0);
                $interval = $user->flat_interval;
                if ($interval === FlatInterval::Monatlich) {
                    foreach ($byMonth as $ym => $sum) {
                        $byMonth[$ym] = $sum + $flat;
                    }
                } elseif ($interval === FlatInterval::ProEinsatz) {
                    foreach ($daysByUserMonth[$uid] ?? [] as $ym => $days) {
                        if (array_key_exists($ym, $byMonth)) {
                            $byMonth[$ym] += $flat * count($days);
                        }
                    }
                } else { // Einmalig → erster Monat des Zeitraums
                    $byMonth[$firstMonth] += $flat;
                }
            }
        }

        if (array_sum($byMonth) <= 0) {
            return [];
        }

        $series = [];
        foreach ($buckets as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => round($byMonth[$bucket['key']] ?? 0.0, 2)];
        }

        return $series;
    }

    /**
     * Auszahlungen (€) je Externem — Top 15, nur positive Beträge.
     *
     * @param  array<int, array{user: User, model: CompensationModel|null, minutes: int, basis: string, amount: float}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function payoutByUserSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $row): bool => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->take(15)
            ->map(static fn(array $row): array => [
                'x' => (string) $row['user']->name,
                'y' => round((float) $row['amount'], 2),
            ])
            ->all());
    }
}
