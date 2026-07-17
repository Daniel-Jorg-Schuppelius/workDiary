<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\SafetyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Sicherheits-Auswertung (Feature 013): Ereignisse je Art und Schweregrad im
 * Zeitraum sowie offen vs. geschlossen.
 */
class SafetyReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View {
        Gate::authorize('viewAny', SafetyEvent::class);
        unset($request);

        [$fromDate, $toDate] = $this->globalDateRangeBounds();

        $events = SafetyEvent::query()
            ->whereBetween('occurred_at', [$fromDate, $toDate])
            ->get(['kind', 'severity', 'status']);

        $byKind = [];
        foreach (SafetyEventKind::cases() as $kind) {
            $byKind[$kind->value] = $events->where('kind', $kind)->count();
        }

        $bySeverity = [];
        foreach (SafetyEventSeverity::cases() as $severity) {
            $bySeverity[$severity->value] = $events->where('severity', $severity)->count();
        }

        $closed = $events->where('status', SafetyEventStatus::Closed)->count();
        $open = $events->count() - $closed;

        return view('reports.safety', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'total' => $events->count(),
            'byKind' => $byKind,
            'bySeverity' => $bySeverity,
            'open' => $open,
            'closed' => $closed,
        ]);
    }
}
