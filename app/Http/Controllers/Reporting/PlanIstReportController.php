<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Team, User};
use App\Services\Reporting\PlanIstReportBuilder;
use App\Services\SqidEncoder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Plan/Ist-Anwesenheits-Report (MVP-018): persönliche Sicht plus Team-/
 * Org-Aggregation (Rang 38, Permissions `report.presence.team`/
 * `.organization`) mit Drilldown auf die Personen-Sicht.
 */
class PlanIstReportController extends Controller {
    public function __construct(private readonly PlanIstReportBuilder $builder) {}

    public function presence(Request $request): View {
        /** @var User $viewer */
        $viewer = Auth::user();

        // Drilldown (Rang 38): Team-/Org-Berechtigte dürfen die Personen-Sicht
        // anderer Mitarbeitender öffnen — immer org-gescopt aufgelöst.
        $user = $viewer;
        if ($request->filled('user')) {
            $targetId = app(SqidEncoder::class)->decode(User::class, (string) $request->query('user'));
            $target = $targetId !== null
                ? User::query()->whereKey($targetId)->first()
                : null;

            if ($target !== null && (int) $target->id !== (int) $viewer->id) {
                abort_unless($this->canViewOthers($viewer, $target), 403);
                $user = $target;
            }
        }

        [$from, $to] = $this->range($request);

        $rows = $this->builder->presenceFor($user, $from, $to);

        $totals = [
            'plan_minutes' => array_sum(array_column($rows, 'plan_minutes')),
            'actual_minutes' => array_sum(array_column($rows, 'actual_minutes')),
            'delta_minutes' => array_sum(array_column($rows, 'delta_minutes')),
            'warnings' => array_sum(array_map(fn ($r) => count($r['warnings']), $rows)),
        ];

        return view('reports.plan-ist.presence', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'reportUser' => $user,
        ]);
    }

    /** Team-Sicht (Rang 38): Summen je Mitglied der eigenen Teams. */
    public function team(Request $request): View {
        /** @var User $viewer */
        $viewer = Auth::user();
        abort_unless($viewer->can(Permission::ReportPresenceTeam->value) || $viewer->isAdmin(), 403);

        $teams = $viewer->teams()->whereNull('archived_at')->orderBy('name')->get();
        if ($viewer->isAdmin() || $viewer->can(Permission::ReportPresenceOrganization->value)) {
            $teams = Team::query()->whereNull('archived_at')->orderBy('name')->get();
        }

        $team = null;
        if ($request->filled('team')) {
            $teamId = app(SqidEncoder::class)->decode(Team::class, (string) $request->query('team'));
            $team = $teams->first(fn (Team $t): bool => (int) $t->id === (int) $teamId);
        }
        $team ??= $teams->first();
        abort_if($team === null, 404, 'Kein Team verfügbar.');

        [$from, $to] = $this->range($request);

        $users = $team->members()->orderBy('name')->get();
        $summary = $this->builder->presenceSummaryFor($users, $from, $to);

        return view('reports.plan-ist.summary', [
            'scope' => 'team',
            'team' => $team,
            'teams' => $teams,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Org-Sicht (Rang 38): Summen je Mitarbeiter:in der Organisation. */
    public function organization(Request $request): View {
        /** @var User $viewer */
        $viewer = Auth::user();
        Gate::authorize(Permission::ReportPresenceOrganization->value);

        [$from, $to] = $this->range($request);

        $users = User::query()->orderBy('name')->get();
        $summary = $this->builder->presenceSummaryFor($users, $from, $to);

        return view('reports.plan-ist.summary', [
            'scope' => 'organization',
            'team' => null,
            'teams' => collect(),
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Drilldown-Berechtigung: Team-Recht deckt Mitglieder eigener Teams, Org-Recht alle. */
    private function canViewOthers(User $viewer, User $target): bool {
        if ($viewer->isAdmin() || $viewer->can(Permission::ReportPresenceOrganization->value)) {
            return true;
        }

        if (! $viewer->can(Permission::ReportPresenceTeam->value)) {
            return false;
        }

        $viewerTeamIds = $viewer->teams()->pluck('teams.id');

        return $target->teams()->whereIn('teams.id', $viewerTeamIds)->exists();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(Request $request): array {
        $now = CarbonImmutable::now();
        $from = $request->filled('from')
            ? CarbonImmutable::parse((string) $request->input('from'))
            : $now->startOfMonth();
        $to = $request->filled('to')
            ? CarbonImmutable::parse((string) $request->input('to'))
            : $now->endOfMonth();

        return [$from, $to];
    }
}
