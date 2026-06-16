<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectDetailsReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Project, TimeEntry, User};
use App\Support\{CsvNumber, XlsxExport};
use App\Support\Sqid;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Projekt-Details-Report: Für ein gewähltes Projekt zeigt Monatswerte
 * (12 Monate eines Jahres) sowie Aufschlüsselung pro Mitarbeiter.
 *
 * Pattern angelehnt an Kimai's ProjectDetailsController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class ProjectDetailsReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isAdmin = $authUser?->isAdmin() ?? false;

        $year = (int) $this->globalDateRange()['from']->year;
        $year = max(2000, min(2100, $year));
        $rawProjectId = $request->query('project_id');
        $projectId = Sqid::decodeOrNumeric(Project::class, $rawProjectId);
        $projectId ??= 0;

        $projects = $this->loadAccessibleProjects($isAdmin, $userId);

        $project = $projectId > 0 ? $projects->firstWhere('id', $projectId) : null;
        if (! $project instanceof Project) {
            $project = $projects->first();
            $projectId = $project instanceof Project ? (int) $project->id : 0;
        }

        $aggregate = $this->aggregateYear($project, $year, $isAdmin, $userId);
        $monthMatrix = $aggregate['monthMatrix'];
        $byUser = $aggregate['byUser'];
        $yearMinutes = $aggregate['yearMinutes'];
        $yearRate = $aggregate['yearRate'];

        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');
        $byUser = $this->sortByUserByName($byUser, $users);

        $monthLabels = $this->buildMonthLabels($year);

        if ($request->query('export') === 'csv' && $project instanceof Project) {
            return $this->exportCsv($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate);
        }
        if ($request->query('export') === 'xlsx' && $project instanceof Project) {
            return $this->exportXlsx($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate);
        }
        if ($request->query('export') === 'pdf' && $project instanceof Project) {
            return $this->exportPdf($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate);
        }

        return view('reports.project-details', [
            'year' => $year,
            'projectId' => $projectId,
            'project' => $project,
            'projects' => $projects,
            'monthMatrix' => $monthMatrix,
            'monthLabels' => $monthLabels,
            'byUser' => $byUser,
            'users' => $users,
            'yearMinutes' => $yearMinutes,
            'yearRate' => $yearRate,
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    private function loadAccessibleProjects(bool $isAdmin, int $userId): Collection {
        $projectsQuery = Project::with('customer')->orderBy('name');
        if (! $isAdmin) {
            $accessibleIds = TimeEntry::query()
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('project_id')
                ->all();
            $projectsQuery->whereIn('id', $accessibleIds);
        }

        /** @var Collection<int, Project> $projects */
        $projects = $projectsQuery->get();

        return $projects;
    }

    /**
     * @return array{monthMatrix: array<int, array{minutes: int, rate: float}>, byUser: array<int, array{minutes: int, rate: float}>, yearMinutes: int, yearRate: float}
     */
    private function aggregateYear(?Project $project, int $year, bool $isAdmin, int $userId): array {
        /** @var array<int, array{minutes: int, rate: float}> $monthMatrix */
        $monthMatrix = array_fill(1, 12, ['minutes' => 0, 'rate' => 0.0]);
        /** @var array<int, array{minutes: int, rate: float}> $byUser */
        $byUser = [];
        $yearMinutes = 0;
        $yearRate = 0.0;

        if (! $project instanceof Project) {
            return ['monthMatrix' => $monthMatrix, 'byUser' => $byUser, 'yearMinutes' => $yearMinutes, 'yearRate' => $yearRate];
        }

        $start = Carbon::create($year, 1, 1, 0, 0, 0) ?: Carbon::now()->startOfYear();
        $end = $start->copy()->endOfYear();

        $entries = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select('user_id', 'date', 'minutes', 'rate')
            ->get();
        if (! $isAdmin) {
            $entries = $entries->where('user_id', $userId);
        }

        foreach ($entries as $e) {
            $month = (int) Carbon::parse((string) $e->date)->month;
            $monthMatrix[$month] = [
                'minutes' => (int) ($monthMatrix[$month]['minutes'] ?? 0) + (int) $e->minutes,
                'rate' => (float) ($monthMatrix[$month]['rate'] ?? 0.0) + (float) $e->rate,
            ];
            $uid = (int) $e->user_id;
            $byUser[$uid] = [
                'minutes' => (int) ($byUser[$uid]['minutes'] ?? 0) + (int) $e->minutes,
                'rate' => (float) ($byUser[$uid]['rate'] ?? 0.0) + (float) $e->rate,
            ];
            $yearMinutes += (int) $e->minutes;
            $yearRate += (float) $e->rate;
        }

        return ['monthMatrix' => $monthMatrix, 'byUser' => $byUser, 'yearMinutes' => $yearMinutes, 'yearRate' => $yearRate];
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     * @return array<int, array{minutes: int, rate: float}>
     */
    private function sortByUserByName(array $byUser, Collection $users): array {
        uksort($byUser, function ($a, $b) use ($users): int {
            $ua = $users->get($a);
            $ub = $users->get($b);
            $na = $ua instanceof User ? $ua->name : '~';
            $nb = $ub instanceof User ? $ub->name : '~';

            return strnatcasecmp($na, $nb);
        });

        return $byUser;
    }

    /**
     * @return array<int, string>
     */
    private function buildMonthLabels(int $year): array {
        $monthLabels = [];
        $locale = app()->getLocale();
        for ($i = 1; $i <= 12; $i++) {
            $d = Carbon::create($year, $i, 1) ?: Carbon::now();
            $d->locale($locale);
            $monthLabels[$i] = $d->isoFormat('MMM');
        }

        return $monthLabels;
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     */
    private function exportCsv(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate): Response {
        $filename = sprintf('projekt-%d-%d.csv', $project->id, $year);
        $rows = [['Monat', 'Minuten', 'Erloes']];
        foreach ($monthMatrix as $idx => $row) {
            $rows[] = [$monthLabels[$idx] ?? (string) $idx, (int) $row['minutes'], CsvNumber::decimal((float) $row['rate'])];
        }
        $rows[] = ['Gesamt', $yearMinutes, CsvNumber::decimal($yearRate)];
        $rows[] = [];
        $rows[] = ['Mitarbeiter', 'Minuten', 'Erloes'];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $rows[] = [(string) $name, (int) $row['minutes'], CsvNumber::decimal((float) $row['rate'])];
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }

                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     */
    private function exportXlsx(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate): SymfonyResponse {
        $filename = sprintf('projekt-%d-%d.xlsx', $project->id, $year);

        // Bauen einer kombinierten Tabelle: erst Monate, dann separator, dann Mitarbeiter.
        $headers = ['Bereich', 'Bezeichnung', 'Minuten', 'Erloes'];
        $rows = [];
        foreach ($monthMatrix as $idx => $row) {
            $rows[] = ['Monat', $monthLabels[$idx] ?? (string) $idx, (int) $row['minutes'], (float) $row['rate']];
        }
        $rows[] = ['Monat', 'Gesamt', (int) $yearMinutes, (float) $yearRate];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $rows[] = ['Mitarbeiter', (string) $name, (int) $row['minutes'], (float) $row['rate']];
        }

        return XlsxExport::streamFromArray($filename, $headers, $rows);
    }

    /**
     * @param  array<int, array{minutes: int, rate: float}>  $monthMatrix
     * @param  array<int, string>  $monthLabels
     * @param  array<int, array{minutes: int, rate: float}>  $byUser
     * @param  Collection<int, User>  $users
     */
    private function exportPdf(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate): SymfonyResponse {
        $filename = sprintf('projekt-%d-%d.pdf', $project->id, $year);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.project-details', [
            'project' => $project,
            'year' => $year,
            'monthMatrix' => $monthMatrix,
            'monthLabels' => $monthLabels,
            'byUser' => $byUser,
            'users' => $users,
            'yearMinutes' => $yearMinutes,
            'yearRate' => $yearRate,
        ])->setPaper('a4');

        return $pdf->download($filename);
    }
}
