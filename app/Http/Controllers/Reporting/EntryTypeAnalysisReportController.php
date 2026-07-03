<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeAnalysisReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Diary\Status as DiaryStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Customer, EntryType, User};
use App\Services\Reporting\EntryTypeAnalysisReportBuilder;
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntryTypeAnalysisReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function __construct(private readonly EntryTypeAnalysisReportBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $range = $this->globalDateRange();
        $from = $range['from']->startOfDay();
        $to = $range['to']->endOfDay();

        $rawCustomerId = $request->query('customer_id');
        $rawUserId = $request->query('user_id');
        $rawEntryTypeId = $request->query('entry_type_id');

        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomerId);

        $userId = Sqid::decodeOrNumeric(User::class, $rawUserId);

        $entryTypeFilter = Sqid::decodeOrNumeric(EntryType::class, $rawEntryTypeId);
        $statusFilter = $request->filled('status') ? (int) $request->integer('status') : null;

        $rows = $this->builder->build($from, $to, $customerId, $userId, $entryTypeFilter, $statusFilter);

        $exportContext = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'customer_id' => $customerId,
            'user_id' => $userId,
            'entry_type_id' => $entryTypeFilter,
            'status' => $statusFilter,
        ];

        if ($request->query('export') === 'csv') {
            $this->auditExport($request, 'entry-types-analysis', 'csv', $exportContext);

            return $this->exportCsv($rows, $from->toDateString(), $to->toDateString(), $exportContext);
        }

        if ($request->query('export') === 'pdf') {
            $this->auditExport($request, 'entry-types-analysis', 'pdf', $exportContext);

            return $this->exportPdf($rows, $range['label'], $from->toDateString(), $to->toDateString());
        }

        return view('reports.entry-types', [
            'rows' => $rows,
            'label' => $range['label'],
            'from' => $from,
            'to' => $to,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'reportUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'entryTypes' => EntryType::query()->ordered()->get(['id', 'label']),
            'customerId' => $customerId,
            'userId' => $userId,
            'entryTypeFilter' => $entryTypeFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * @param  list<array{
     *   entryTypeId:int,
     *   entryTypeName:string,
     *   entryCount:int,
     *   avgPlannedMinutes:float,
     *   avgActualMinutes:float,
     *   planActualRatio:float|null,
     *   overrunCount:int,
     *   overrunShare:float,
     *   reworkCount:int,
     *   reworkShare:float,
     *   escalationCount:int,
     *   escalationShare:float,
     *   firstTimeRightShare:float,
     *   medianActualMinutes:float,
     *   p90ActualMinutes:float
     * }>             $rows
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $rows, string $from, string $to, array $filters): Response {
        $filename = sprintf('auftragstypanalyse_%s_%s.csv', $from, $to);

        $out = [];
        $out[] = [
            'Auftragstyp',
            'Auftraege',
            'DurchschnittPlanMinuten',
            'DurchschnittIstMinuten',
            'PlanIstVerhaeltnis',
            'UeberzugAnzahl',
            'UeberzugProzent',
            'NacharbeitAnzahl',
            'NacharbeitProzent',
            'EscalationAnzahl',
            'EscalationProzent',
            'FirstTimeRightProzent',
            'MedianIstMinuten',
            'P90IstMinuten',
        ];

        foreach ($rows as $row) {
            $out[] = [
                $row['entryTypeName'],
                $row['entryCount'],
                number_format((float) $row['avgPlannedMinutes'], 2, '.', ''),
                number_format((float) $row['avgActualMinutes'], 2, '.', ''),
                $row['planActualRatio'] === null ? '' : number_format((float) $row['planActualRatio'], 3, '.', ''),
                $row['overrunCount'],
                number_format((float) $row['overrunShare'], 2, '.', ''),
                $row['reworkCount'],
                number_format((float) $row['reworkShare'], 2, '.', ''),
                $row['escalationCount'],
                number_format((float) $row['escalationShare'], 2, '.', ''),
                number_format((float) $row['firstTimeRightShare'], 2, '.', ''),
                number_format((float) $row['medianActualMinutes'], 2, '.', ''),
                number_format((float) $row['p90ActualMinutes'], 2, '.', ''),
            ];
        }

        return $this->csvWithMetadata($out, $filename, 'entry-types-analysis', $filters);
    }

    /**
     * @param  list<array{
     *   entryTypeId:int,
     *   entryTypeName:string,
     *   entryCount:int,
     *   avgPlannedMinutes:float,
     *   avgActualMinutes:float,
     *   planActualRatio:float|null,
     *   overrunCount:int,
     *   overrunShare:float,
     *   reworkCount:int,
     *   reworkShare:float,
     *   escalationCount:int,
     *   escalationShare:float,
     *   firstTimeRightShare:float,
     *   medianActualMinutes:float,
     *   p90ActualMinutes:float
     * }>  $rows
     */
    private function exportPdf(array $rows, string $label, string $from, string $to): SymfonyResponse {
        $filename = sprintf('auftragstypanalyse_%s_%s.pdf', $from, $to);

        return $this->pdfDownload('reports.pdf.entry-types', [
            'rows' => $rows,
            'label' => $label,
        ], $filename, 'landscape');
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array {
        $options = [];
        foreach (DiaryStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
