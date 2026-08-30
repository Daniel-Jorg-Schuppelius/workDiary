<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Services\Learning\LearningReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Kursanalyse (Feature 149, MVP-747).
 *
 * Zeigt **Quoten und Auffälligkeiten**, keine Personenprofile: wer wie
 * lange gelernt hat, gehört ins Betreuer-Cockpit, wo es an ein Recht und
 * einen Zuständigkeitsbereich gebunden ist.
 */
class LearningReportController extends Controller {
    use RendersReportPdf;

    use ResolvesCurrentOrganization;

    use WritesReportCsv;

    public function __construct(
        private readonly LearningReportService $reports,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::LearningViewAny->value);

        $organization = $this->currentOrganization();

        $data = [
            'summary' => $this->reports->summary($organization),
            'completion' => $this->reports->courseCompletion($organization),
            'dropOffs' => $this->reports->dropOffPoints($organization),
            'questions' => $this->reports->questionDifficulty($organization),
            'minGroup' => LearningReportService::MIN_GROUP,
        ];

        $export = (string) $request->query('export', '');

        if ($export === 'pdf') {
            return $this->pdfDownload('reports.pdf.learning', $data, 'lernen.pdf', 'portrait', $request, 'learning');
        }

        if ($export === 'csv' || $export === 'xlsx') {
            return $this->csvWithMetadata($this->exportRows($data), 'lernen.csv', 'learning', [], $request);
        }

        return view('reports.learning', $data);
    }

    /**
     * Exportzeilen. **Unterdrückte Quoten bleiben unterdrückt** — ein Export
     * darf die Schwelle von fünf Einschreibungen nicht aushebeln, sonst wäre
     * die Datensparsamkeit eine Anzeigefrage statt einer Regel.
     *
     * @param  array<string, mixed>  $data
     * @return list<list<string|int|null>>
     */
    private function exportRows(array $data): array {
        $rows = [[
            (string) __('learning.field.course'),
            (string) __('learning.field.enrolled'),
            (string) __('learning.field.completed'),
            (string) __('learning.field.completion_rate'),
        ]];

        /** @var list<array<string, mixed>> $completion */
        $completion = $data['completion'] ?? [];

        foreach ($completion as $row) {
            $rows[] = [
                (string) ($row['course']->title ?? ''),
                (int) ($row['enrolled'] ?? 0),
                (int) ($row['completed'] ?? 0),
                // null statt einer Zahl, wenn die Gruppe zu klein ist.
                $row['rate'] === null ? (string) __('learning.field.suppressed') : $row['rate'],
            ];
        }

        return $rows;
    }
}
