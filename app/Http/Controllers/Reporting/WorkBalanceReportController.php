<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkBalanceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Reporting\WorkBalanceCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Unified Work-Balance report: combines Soll (FlexCalculator),
 * Anwesenheit (Attendance), Erfassung (TimeEntry by kind/activity) and
 * the resulting balance for the currently selected global date range
 * (Header-Widget) or an explicit `?from=&to=` / `?year=&month=` override.
 */
class WorkBalanceReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(protected WorkBalanceCalculator $calc) {
    }

    public function index(Request $request): View|SymfonyResponse {
        /** @var User $authUser */
        $authUser = Auth::user();
        $user = $this->resolveTargetUser($request, $authUser);
        $selectableUsers = $authUser->isAdmin() ? $this->loadSelectableUsers() : null;

        [$from, $to, $label] = $this->resolveRange($request);

        $period = $this->calc->range($user, $from, $to);

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('reports.work-balance-pdf', [
                'user' => $user,
                'period' => $period,
                'label' => $label,
            ])->setPaper('a4', 'portrait');

            $filename = sprintf(
                'arbeitsbilanz-%s-%s_%s.pdf',
                $user->id,
                $from->format('Ymd'),
                $to->format('Ymd'),
            );

            return $pdf->download($filename);
        }

        /** @var View $view */
        $view = view('reports.work-balance', [
            'user' => $user,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'selectableUsers' => $selectableUsers,
        ]);

        return $view;
    }

    /**
     * Wer wird angezeigt? Standard: eingeloggter Nutzer.
     * Admin darf per ?user=ID jeden anderen Nutzer sehen, sonst 403.
     */
    private function resolveTargetUser(Request $request, User $authUser): User {
        if (! $request->filled('user')) {
            return $authUser;
        }

        $requestedId = (int) $request->integer('user');
        if ($requestedId === (int) $authUser->id) {
            return $authUser;
        }

        if (! $authUser->isAdmin()) {
            throw new AccessDeniedHttpException('Nur Admins dürfen die Arbeitsbilanz anderer Nutzer einsehen.');
        }

        $target = User::query()->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }

    /**
     * @return Collection<int, User>
     */
    private function loadSelectableUsers(): Collection {
        /** @var Collection<int, User> $users */
        $users = User::query()->orderBy('name')->get();

        return $users;
    }

    /**
     * Precedence: explicit ?from/?to → ?year(+?month) → global header range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveRange(Request $request): array {
        if ($request->filled('from') && $request->filled('to')) {
            $from = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();
            $to = CarbonImmutable::parse((string) $request->query('to'))->endOfDay();

            return [$from, $to, $this->formatLabel($from, $to)];
        }

        if ($request->filled('year') && ! $request->filled('month')) {
            $year = max(2000, min(2100, (int) $request->integer('year')));
            $tmp = CarbonImmutable::create($year, 1, 1);
            $base = $tmp instanceof CarbonImmutable ? $tmp : CarbonImmutable::now();
            $from = $base->startOfYear();
            $to = $from->endOfYear();

            return [$from, $to, (string) $year];
        }

        if ($request->filled('year') && $request->filled('month')) {
            $year = max(2000, min(2100, (int) $request->integer('year')));
            $month = max(1, min(12, (int) $request->integer('month')));
            $tmp = CarbonImmutable::create($year, $month, 1);
            $base = $tmp instanceof CarbonImmutable ? $tmp : CarbonImmutable::now();
            $from = $base->startOfMonth();
            $to = $from->endOfMonth();
            CarbonImmutable::setLocale((string) app()->getLocale());

            return [$from, $to, $from->isoFormat('MMMM YYYY')];
        }

        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();
        $label = $range['label'] !== '' ? $range['label'] : $this->formatLabel($from, $to);

        return [$from, $to, $label];
    }

    private function formatLabel(CarbonImmutable $from, CarbonImmutable $to): string {
        return sprintf('%s – %s', $from->format('d.m.Y'), $to->format('d.m.Y'));
    }
}
