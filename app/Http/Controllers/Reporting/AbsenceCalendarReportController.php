<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsenceCalendarReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{SickLeave, Team, User, Vacation};
use App\Services\Absence\VacationBalanceService;
use App\Services\HolidayService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Urlaubsplan-Jahresübersicht + persönliche Fehlzeitenkarte (MVP-520).
 *
 * Jahresübersicht: je Mitarbeiter ein Jahresbalken mit den eingetragenen
 * Fehlzeiträumen (Urlaub/Krank/Sonder/unbezahlt) — Kollisionserkennung auf
 * einen Blick. Datenschutz-Filter: ohne Admin-Sicht (bzw. mit aktivem
 * Anonymisierungs-Schalter) werden fremde Fehlgründe neutral als „abwesend"
 * dargestellt. Drilldown `?user=` öffnet die Fehlzeitenkarte einer Person
 * (Jahreskalender + Urlaubskonto-Block). CSV/PDF exportieren die Zeiträume
 * als Liste („Fehlzeiträume") mit Kalender- und effektiven Arbeitstagen.
 */
class AbsenceCalendarReportController extends Controller {
    use RendersReportPdf;
    use ResolvesReportScope;
    use WritesReportCsv;

    public function __construct(
        private readonly VacationBalanceService $balanceService,
        private readonly HolidayService $holidayService,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        /** @var User $viewer */
        $viewer = Auth::user();
        $isAdmin = $this->viewerIsAdmin();

        $year = (int) $request->input('year', CarbonImmutable::now()->year);
        $year = max(2000, min(2100, $year));
        $yearStart = CarbonImmutable::parse(sprintf('%d-01-01', $year))->startOfDay();
        $yearEnd = $yearStart->endOfYear();
        $daysInYear = (int) $yearStart->diffInDays($yearEnd->addDay());

        // Datenschutz-Filter: Nicht-Admins sehen fremde Fehlgründe IMMER
        // neutral; Admins können die neutrale Sicht (z. B. für den Aushang)
        // explizit zuschalten.
        $anonymize = ! $isAdmin || $request->boolean('anon');

        // Personenkreis: Admin sieht alle (optional teamgefiltert), sonst nur sich selbst.
        $teamSqid = (string) $request->input('team', '');
        $teamId = $teamSqid !== '' ? Sqid::decodeOrNumeric(Team::class, $teamSqid) : null;

        $usersQuery = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->orderBy('name');
        if (! $isAdmin) {
            $usersQuery->whereKey($viewer->getKey());
        } elseif ($teamId !== null) {
            $usersQuery->whereHas('teams', fn ($q) => $q->whereKey($teamId));
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = $usersQuery->get(['id', 'name']);
        $userIds = array_values($users->pluck('id')->map(fn ($v): int => (int) $v)->all());

        $spans = $this->absenceSpans($userIds, $yearStart, $yearEnd, $year);

        // Einzelperson → Fehlzeitenkarte.
        $detailSqid = (string) $request->input('user', '');
        if ($detailSqid !== '') {
            $detailId = Sqid::decodeOrNumeric(User::class, $detailSqid);
            $detailUser = $users->firstWhere('id', $detailId);
            if ($detailUser === null) {
                abort(403);
            }

            return $this->card($request, $detailUser, $spans[$detailId] ?? [], $year, $anonymize && $detailId !== (int) $viewer->getKey());
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($users, $spans, $year, $anonymize, (int) $viewer->getKey(), $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($users, $spans, $year, $anonymize, (int) $viewer->getKey(), $request);
        }

        // Anzeige-Zeilen: Balkenpositionen in Prozent des Jahres.
        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $bars = [];
            foreach ($spans[$uid] ?? [] as $span) {
                $neutral = $anonymize && $uid !== (int) $viewer->getKey();
                $bars[] = [
                    'left' => round(((int) $yearStart->diffInDays($span['from'])) / $daysInYear * 100, 3),
                    'width' => max(round(((int) $span['from']->diffInDays($span['to']->addDay())) / $daysInYear * 100, 3), 0.2),
                    'label' => $neutral ? (string) __('abwesend') : $span['label'],
                    'tone' => $neutral ? 'neutral' : $span['tone'],
                    'from' => $span['from']->toDateString(),
                    'to' => $span['to']->toDateString(),
                ];
            }
            $rows[] = ['user' => $u, 'sqid' => Sqid::encode(User::class, $uid), 'bars' => $bars];
        }

        $teams = $isAdmin
            ? Team::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('reports.absence-calendar', [
            'rows' => $rows,
            'year' => $year,
            'anonymize' => $anonymize,
            'isAdmin' => $isAdmin,
            'teams' => $teams,
            'teamFilter' => $teamSqid,
            'monthStarts' => $this->monthScale($yearStart, $daysInYear),
            'legend' => $this->legend(),
        ]);
    }

    /**
     * Fehlzeitenkarte einer Person: 12 Monatszeilen × 31 Tageszellen +
     * Urlaubskonto-Block + Statistik der effektiven Tage je Fehlgrund.
     *
     * @param  list<array{from: CarbonImmutable, to: CarbonImmutable, type: string, label: string, tone: string}>  $spans
     */
    private function card(Request $request, User $user, array $spans, int $year, bool $neutral): View {
        $yearStart = CarbonImmutable::parse(sprintf('%d-01-01', $year));

        // Tag → Fehlzeit-Slot auflösen (letzter Eintrag gewinnt bei Überlappung).
        /** @var array<string, array{label: string, tone: string, short: string}> $byDay */
        $byDay = [];
        foreach ($spans as $span) {
            $cursor = $span['from'];
            while ($cursor->lessThanOrEqualTo($span['to'])) {
                $byDay[$cursor->toDateString()] = [
                    'label' => $neutral ? (string) __('abwesend') : $span['label'],
                    'tone' => $neutral ? 'neutral' : $span['tone'],
                    'short' => $neutral ? '×' : mb_substr($span['label'], 0, 1),
                ];
                $cursor = $cursor->addDay();
            }
        }

        // Statistik: Kalendertage + effektive Arbeitstage je Fehlgrund.
        $stats = [];
        foreach ($spans as $span) {
            $key = $neutral ? (string) __('abwesend') : $span['label'];
            $stats[$key] ??= ['calendar' => 0, 'effective' => 0.0, 'tone' => $neutral ? 'neutral' : $span['tone']];
            $stats[$key]['calendar'] += (int) $span['from']->diffInDays($span['to']->addDay());
            $stats[$key]['effective'] += $this->balanceService->workingDaysInYear($span['from'], $span['to'], $year);
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $yearStart->setMonth($m);
            $days = [];
            for ($d = 1; $d <= 31; $d++) {
                if ($d > $monthStart->daysInMonth) {
                    $days[] = null;
                    continue;
                }
                $date = $monthStart->setDay($d);
                $key = $date->toDateString();
                $days[] = [
                    'day' => $d,
                    'weekend' => $date->isWeekend(),
                    'holiday' => $this->holidayService->isHoliday($date),
                    'absence' => $byDay[$key] ?? null,
                ];
            }
            $months[] = ['label' => $monthStart->translatedFormat('F'), 'days' => $days];
        }

        return view('reports.absence-card', [
            'user' => $user,
            'year' => $year,
            'months' => $months,
            'stats' => $stats,
            'balance' => $this->balanceService->balanceFor((int) $user->id, $year),
            'neutral' => $neutral,
            'legend' => $this->legend(),
        ]);
    }

    /**
     * Genehmigte Fehlzeiträume je Nutzer, auf das Jahr beschnitten.
     *
     * @param  list<int>  $userIds
     * @return array<int, list<array{from: CarbonImmutable, to: CarbonImmutable, type: string, label: string, tone: string}>>
     */
    private function absenceSpans(array $userIds, CarbonImmutable $yearStart, CarbonImmutable $yearEnd, int $year): array {
        $result = [];
        if ($userIds === []) {
            return $result;
        }

        $clip = static function (string $start, string $end) use ($yearStart, $yearEnd): array {
            $from = CarbonImmutable::parse($start);
            $to = CarbonImmutable::parse($end);

            return [
                $from->lessThan($yearStart) ? $yearStart : $from,
                $to->greaterThan($yearEnd) ? $yearEnd : $to,
            ];
        };

        Vacation::query()
            ->whereIn('user_id', $userIds)
            ->where('status', VacationStatus::Approved->value)
            ->whereDate('start_date', '<=', $yearEnd->toDateString())
            ->whereDate('end_date', '>=', $yearStart->toDateString())
            ->orderBy('start_date')
            ->get(['user_id', 'start_date', 'end_date', 'type'])
            ->each(function (Vacation $v) use (&$result, $clip): void {
                [$from, $to] = $clip($v->start_date->toDateString(), $v->end_date->toDateString());
                $result[(int) $v->user_id][] = [
                    'from' => $from,
                    'to' => $to,
                    'type' => $v->type->value,
                    'label' => $v->type->label(),
                    'tone' => match ($v->type) {
                        VacationType::Vacation => 'warning',
                        VacationType::Special => 'info',
                        default => 'neutral',
                    },
                ];
            });

        SickLeave::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('start_date', '<=', $yearEnd->toDateString())
            ->whereDate('end_date', '>=', $yearStart->toDateString())
            ->orderBy('start_date')
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(function (SickLeave $s) use (&$result, $clip): void {
                [$from, $to] = $clip($s->start_date->toDateString(), $s->end_date->toDateString());
                $result[(int) $s->user_id][] = [
                    'from' => $from,
                    'to' => $to,
                    'type' => 'sick',
                    'label' => (string) __('Krank'),
                    'tone' => 'error',
                ];
            });

        return $result;
    }

    /** @return list<array{left: float, label: string}> Monatsskala in Prozent. */
    private function monthScale(CarbonImmutable $yearStart, int $daysInYear): array {
        $scale = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthStart = $yearStart->setMonth($m)->startOfMonth();
            $scale[] = [
                'left' => round(((int) $yearStart->diffInDays($monthStart)) / $daysInYear * 100, 3),
                'label' => $monthStart->translatedFormat('M'),
            ];
        }

        return $scale;
    }

    /** @return array<string, string> Ton je Legenden-Label. */
    private function legend(): array {
        return [
            (string) __('Urlaub') => 'warning',
            (string) __('Krank') => 'error',
            (string) __('Sonderurlaub') => 'info',
            (string) __('abwesend') => 'neutral',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  array<int, list<array{from: CarbonImmutable, to: CarbonImmutable, type: string, label: string, tone: string}>>  $spans
     */
    private function exportCsv($users, array $spans, int $year, bool $anonymize, int $viewerId, Request $request): Response {
        $out = [[
            (string) __('Mitarbeiter'),
            (string) __('Von'),
            (string) __('Bis'),
            (string) __('Fehlgrund'),
            (string) __('Kalendertage'),
            (string) __('Effektive Arbeitstage'),
        ]];
        foreach ($users as $u) {
            foreach ($spans[(int) $u->id] ?? [] as $span) {
                $neutral = $anonymize && (int) $u->id !== $viewerId;
                $out[] = [
                    $u->name,
                    $span['from']->toDateString(),
                    $span['to']->toDateString(),
                    $neutral ? (string) __('abwesend') : $span['label'],
                    (string) ((int) $span['from']->diffInDays($span['to']->addDay())),
                    number_format($this->balanceService->workingDaysInYear($span['from'], $span['to'], $year), 2, '.', ''),
                ];
            }
        }

        return $this->csvWithMetadata($out, sprintf('fehlzeitraeume_%d.csv', $year), 'absence_calendar', ['year' => $year], $request);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  array<int, list<array{from: CarbonImmutable, to: CarbonImmutable, type: string, label: string, tone: string}>>  $spans
     */
    private function exportPdf($users, array $spans, int $year, bool $anonymize, int $viewerId, Request $request): SymfonyResponse {
        $rows = [];
        foreach ($users as $u) {
            $items = [];
            foreach ($spans[(int) $u->id] ?? [] as $span) {
                $neutral = $anonymize && (int) $u->id !== $viewerId;
                $items[] = [
                    'from' => $span['from']->toDateString(),
                    'to' => $span['to']->toDateString(),
                    'label' => $neutral ? (string) __('abwesend') : $span['label'],
                    'calendar' => (int) $span['from']->diffInDays($span['to']->addDay()),
                    'effective' => $this->balanceService->workingDaysInYear($span['from'], $span['to'], $year),
                ];
            }
            if ($items !== []) {
                $rows[] = ['user' => $u, 'items' => $items];
            }
        }

        return $this->pdfDownload('reports.pdf.absence-calendar', [
            'rows' => $rows,
            'year' => $year,
        ], sprintf('fehlzeitraeume_%d.pdf', $year), request: $request, reportCode: 'absence_calendar', filters: ['year' => $year]);
    }
}
