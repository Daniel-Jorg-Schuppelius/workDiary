<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $isAdmin = Auth::user()?->isAdmin() ?? false;

        $year = (int) $request->input('year', $this->globalDateRange()['from']->year);
        $year = max(2000, min(2100, $year));
        $projectId = $request->integer('project_id');

        $projectsQuery = Project::with('customer')->orderBy('name');
        if (! $isAdmin) {
            // Nur Projekte, in denen der Nutzer Zeit erfasst hat (oder ist Mitglied).
            $accessibleIds = TimeEntry::query()
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('project_id')
                ->all();
            $projectsQuery->whereIn('id', $accessibleIds);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, Project> $projects */
        $projects = $projectsQuery->get();

        $project = null;
        if ($projectId > 0) {
            $project = $projects->firstWhere('id', $projectId);
        }
        if (! $project instanceof Project) {
            $project = $projects->first();
            $projectId = $project instanceof Project ? (int) $project->id : 0;
        }

        /** @var array<int, array{minutes: int, rate: float}> $monthMatrix */
        $monthMatrix = array_fill(1, 12, ['minutes' => 0, 'rate' => 0.0]);
        /** @var array<int, array{minutes: int, rate: float}> $byUser */
        $byUser = [];
        $yearMinutes = 0;
        $yearRate = 0.0;

        if ($project instanceof Project) {
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
        }

        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');
        uksort($byUser, function ($a, $b) use ($users): int {
            $ua = $users->get($a);
            $ub = $users->get($b);
            $na = $ua instanceof User ? $ua->name : '~';
            $nb = $ub instanceof User ? $ub->name : '~';
            return strnatcasecmp($na, $nb);
        });

        $monthLabels = [];
        $locale = app()->getLocale();
        for ($i = 1; $i <= 12; $i++) {
            $d = Carbon::create($year, $i, 1) ?: Carbon::now();
            $d->locale($locale);
            $monthLabels[$i] = $d->isoFormat('MMM');
        }

        if ($request->query('export') === 'csv' && $project instanceof Project) {
            return $this->exportCsv($project, $year, $monthMatrix, $monthLabels, $byUser, $users, $yearMinutes, $yearRate);
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
     * @param array<int, array{minutes: int, rate: float}> $monthMatrix
     * @param array<int, string> $monthLabels
     * @param array<int, array{minutes: int, rate: float}> $byUser
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users
     */
    private function exportCsv(Project $project, int $year, array $monthMatrix, array $monthLabels, array $byUser, $users, int $yearMinutes, float $yearRate): Response {
        $filename = sprintf('projekt-%d-%d.csv', $project->id, $year);
        $rows = [['Monat', 'Minuten', 'Erloes']];
        foreach ($monthMatrix as $idx => $row) {
            $rows[] = [$monthLabels[$idx] ?? (string) $idx, (int) $row['minutes'], number_format((float) $row['rate'], 2, '.', '')];
        }
        $rows[] = ['Gesamt', $yearMinutes, number_format($yearRate, 2, '.', '')];
        $rows[] = [];
        $rows[] = ['Mitarbeiter', 'Minuten', 'Erloes'];
        foreach ($byUser as $uid => $row) {
            $userModel = $users->get($uid);
            $name = $userModel instanceof User ? $userModel->name : '#' . $uid;
            $rows[] = [(string) $name, (int) $row['minutes'], number_format((float) $row['rate'], 2, '.', '')];
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
     * @param array<int, array{minutes: int, rate: float}> $monthMatrix
     * @param array<int, string> $monthLabels
     * @param array<int, array{minutes: int, rate: float}> $byUser
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users
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
