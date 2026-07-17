<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{DiaryEntry, User};
use App\Services\Classification\DataQualityInspector;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Datenqualitäts-Report „Objekte ohne Pflichtklassifikation" (Feature 024 →
 * Rang 57): listet Aufträge des Zeitraums mit fehlenden Pflichtklassifikationen,
 * aggregiert nach Domäne, Phase und Schwere, mit Direktlink zum Auftrag (dort
 * lässt sich die Klassifikation nachtragen). Rein lesend — nichts wird erzwungen.
 *
 * Hinweis: Pflichtklassifikationen sind heute an die Auftragsart
 * (`entry_type_code`) gebunden; Assets/Artikel haben keine eigenen
 * Anforderungen und sind daher (noch) nicht Teil des Reports.
 */
class DataQualityReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly DataQualityInspector $inspector) {}

    public function index(Request $request): View {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::ReportView->value), 403);

        $range = $this->globalDateRange();
        [$from, $to] = $this->globalDateRangeBounds();

        $entries = DiaryEntry::query()
            ->with('entryType')
            ->where('is_archived', false)
            ->overlappingDateRange($from->toDateString(), $to->toDateString())
            ->orderByDesc('start_at')
            ->limit(1000)
            ->get();

        $report = $this->inspector->report($entries);

        return view('reports.data-quality', $report + [
            'label' => $range['label'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
