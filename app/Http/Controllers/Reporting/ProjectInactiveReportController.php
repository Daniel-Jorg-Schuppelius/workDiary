<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectInactiveReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Project\ProjectStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Support\{Sqid, XlsxExport};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Inaktive Projekte: Projekte, zu denen im gewählten Zeitraum KEINE
 * TimeEntries existieren. Bulk-Archive-Aktion. CSV-Export.
 *
 * Pattern angelehnt an Kimai's ProjectInactiveController (AGPL-3.0) — eigene
 * Implementierung, kein Code-Reuse.
 */
class ProjectInactiveReportController extends Controller {
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize('viewAny', Project::class);

        $range = $this->globalDateRange();
        $from = $range['from'];
        $to = $range['to'];

        $projects = $this->loadInactiveProjects($from, $to);

        // Letzte Aktivität insgesamt pro Projekt (kann vor dem Range liegen).
        $lastByProject = $this->lastActivityByProject($projects->pluck('id')->all());

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($projects, $lastByProject, $from, $to, $request);
        }
        if ($request->query('export') === 'xlsx') {
            return $this->exportXlsx($projects, $lastByProject, $from, $to);
        }

        /** @var view-string $view */
        $view = 'reports.project-inactive';

        return view($view, [
            'projects' => $projects,
            'lastByProject' => $lastByProject,
            'rangeFrom' => $from,
            'rangeTo' => $to,
        ]);
    }

    public function archive(Request $request): RedirectResponse {
        Gate::authorize('viewAny', Project::class);

        /** @var array<int|string> $rawIds */
        $rawIds = (array) $request->input('project_ids', []);
        /** @var array<int> $ids */
        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = Sqid::decodeOrNumeric(Project::class, (string) $rawId);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        if (count($ids) === 0) {
            return redirect()->route('reports.project-inactive')
                ->with('error', __('Keine Projekte ausgewählt.'));
        }

        /** @var User $auth */
        $auth = Auth::user();
        $orgId = (int) $auth->organization_id;

        $projects = Project::query()
            ->whereIn('id', $ids)
            ->where('organization_id', $orgId)
            ->get();

        $archived = 0;
        foreach ($projects as $project) {
            /** @var Project $project */
            if (! Gate::allows('update', $project)) {
                continue;
            }
            if ($project->status === ProjectStatus::Archived) {
                continue;
            }
            $project->status = ProjectStatus::Archived;
            $project->archived_at = now();
            $project->save();
            $archived++;
        }

        return redirect()->route('reports.project-inactive')
            ->with('success', __(':n Projekt(e) archiviert.', ['n' => $archived]));
    }

    /**
     * @return Collection<int, Project>
     */
    private function loadInactiveProjects(CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Project> $projects */
        $projects = Project::query()
            ->with('customer')
            ->where('status', '!=', ProjectStatus::Archived->value)
            ->whereDoesntHave('timeEntries', function ($q) use ($from, $to): void {
                $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })
            ->orderBy('name')
            ->get();

        return $projects;
    }

    /**
     * @param  array<int>  $projectIds
     * @return array<int, string|null>  project_id => max(date) (Y-m-d) or null
     */
    private function lastActivityByProject(array $projectIds): array {
        if (count($projectIds) === 0) {
            return [];
        }

        /** @var array<int, string|null> $out */
        $out = [];
        TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->select('project_id', DB::raw('MAX(date) as last_date'))
            ->groupBy('project_id')
            ->get()
            ->each(function ($row) use (&$out): void {
                $out[(int) $row->getAttribute('project_id')] = $row->getAttribute('last_date') !== null
                    ? (string) $row->getAttribute('last_date')
                    : null;
            });

        foreach ($projectIds as $id) {
            if (! array_key_exists($id, $out)) {
                $out[$id] = null;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  array<int, string|null>  $lastByProject
     */
    private function exportCsv(Collection $projects, array $lastByProject, CarbonImmutable $from, CarbonImmutable $to, Request $request): Response {
        $filename = sprintf('projekte-inaktiv_%s_%s.csv', $from->toDateString(), $to->toDateString());
        $rows = [['Projekt', 'Kunde', 'Status', 'Letzte Aktivität']];
        foreach ($projects as $project) {
            /** @var Project $project */
            $customer = $project->customer;
            $customerName = $customer instanceof Customer ? (string) $customer->name : '';
            $last = $lastByProject[(int) $project->id] ?? null;
            $rows[] = [
                (string) $project->name,
                $customerName,
                $project->status->value,
                $last ?? '',
            ];
        }

        return $this->csvWithMetadata($rows, $filename, 'project-inactive', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ], $request);
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  array<int, string|null>  $lastByProject
     */
    private function exportXlsx(Collection $projects, array $lastByProject, CarbonImmutable $from, CarbonImmutable $to): SymfonyResponse {
        $filename = sprintf('projekte-inaktiv_%s_%s.xlsx', $from->toDateString(), $to->toDateString());
        $headers = ['Projekt', 'Kunde', 'Status', 'Letzte Aktivität'];
        $rows = [];
        foreach ($projects as $project) {
            /** @var Project $project */
            $customer = $project->customer;
            $customerName = $customer instanceof Customer ? (string) $customer->name : '';
            $last = $lastByProject[(int) $project->id] ?? null;
            $rows[] = [
                (string) $project->name,
                $customerName,
                $project->status->value,
                $last ?? '',
            ];
        }

        return XlsxExport::streamFromArray($filename, $headers, $rows);
    }
}
