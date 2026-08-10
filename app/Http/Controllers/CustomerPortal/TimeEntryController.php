<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CustomerPortal\PortalTimeDetail;
use App\Http\Controllers\Controller;
use App\Models\{TimeEntry, User};
use App\Services\CustomerPortal\PortalVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Projektzeiten im Kundenportal (MVP-511): Umfang und Detailtiefe folgen der
 * kundenspezifischen Freigabe — `summary` zeigt nur Summen, `entries` die
 * Einzelzeilen ohne Beschreibung, `entries_with_description` zusätzlich die
 * Beschreibung ausschließlich veröffentlichter Einträge. Interne Kosten,
 * Sätze, Tags und Integrationsdaten sind nie Portalinhalt.
 */
class TimeEntryController extends Controller {
    public function index(PortalVisibility $visibility): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customer = $user->customer;
        abort_if($customer === null, 404);

        $detail = $visibility->timeDetail($customer);
        abort_if($detail === PortalTimeDetail::None, 404);

        $scope = $visibility->timeScope($customer);
        $base = TimeEntry::query()
            ->whereHas('project', fn(Builder $q) => $q->where('customer_id', $customer->id))
            ->when(
                $scope === PortalVisibility::TIME_SCOPE_PUBLISHED,
                fn(Builder $q) => $q->whereNotNull('customer_visible_at'),
            );

        if ($detail === PortalTimeDetail::Summary) {
            // Nur freigegebene Summen: je Monat und Projekt, keine Einzelzeilen.
            // SUBSTR statt DATE_FORMAT: `date` liegt als 'Y-m-d …' vor und der
            // Ausdruck läuft auf MariaDB wie SQLite (Tests).
            $summaries = (clone $base)
                ->join('projects', 'projects.id', '=', 'time_entries.project_id')
                ->groupBy('projects.name', DB::raw('SUBSTR(time_entries.date, 1, 7)'))
                ->orderByDesc(DB::raw('SUBSTR(time_entries.date, 1, 7)'))
                ->orderBy('projects.name')
                ->get([
                    DB::raw('SUBSTR(time_entries.date, 1, 7) as month'),
                    DB::raw('projects.name as project_name'),
                    DB::raw('SUM(time_entries.minutes) as minutes'),
                ]);

            return view('customer.time-entries.index', [
                'detail' => $detail,
                'summaries' => $summaries,
                'entries' => null,
            ]);
        }

        $entries = $base
            ->with(['project:id,name,customer_id', 'user:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('customer.time-entries.index', [
            'detail' => $detail,
            'summaries' => null,
            'entries' => $entries,
        ]);
    }
}
