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
use App\Models\{TimeEntry, User};
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

    public function index(): View {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($auth->organization_id !== null && $auth->can(Permission::UserPayrollManage->value), 403);

        [$from, $to] = $this->globalDateRangeBounds();
        $monthCount = max(1, count($this->buildMonthsInRange($from, $to)));

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
                $rate = (float) ($user->compensation_rate ?? 0);
                // Auf 2 Dezimalstellen runden (Geldbetrag), damit die Summe der
                // Zeilen mit dem ausgewiesenen Gesamtbetrag übereinstimmt und
                // nicht durch akkumulierte Nachkommastellen abweicht.
                $amount = round($minutes / 60 * $rate, 2);
                $basis = __(':hours × :rate', [
                    'hours' => number_format($minutes / 60, 2, ',', '.') . ' h',
                    'rate' => number_format($rate, 2, ',', '.') . ' €',
                ]);
            } elseif ($model === CompensationModel::Pauschal) {
                $flat = (float) ($user->flat_amount ?? 0);
                $interval = $user->flat_interval;
                if ($interval === FlatInterval::Monatlich) {
                    $amount = $flat * $monthCount;
                    $basis = number_format($flat, 2, ',', '.') . ' € × ' . $monthCount . ' ' . __('Monate');
                } elseif ($interval === FlatInterval::ProEinsatz) {
                    $einsatzDays = TimeEntry::query()
                        ->where('user_id', $user->id)
                        ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                        ->distinct()
                        ->count('date');
                    $amount = $flat * $einsatzDays;
                    $basis = number_format($flat, 2, ',', '.') . ' € × ' . $einsatzDays . ' ' . __('Einsätze');
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
        ]);
    }
}
