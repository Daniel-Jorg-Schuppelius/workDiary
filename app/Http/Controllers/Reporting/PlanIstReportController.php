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
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{Team, User};
use App\Services\Reporting\PlanIstReportBuilder;
use App\Services\SqidEncoder;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Plan/Ist-Anwesenheits-Report (MVP-018): persönliche Sicht plus Team-/
 * Org-Aggregation (Rang 38, Permissions `report.presence.team`/
 * `.organization`) mit Drilldown auf die Personen-Sicht.
 *
 * A14 · MVP-333 (Feature 007): erweiterte Dimensionen Schicht (§2.3),
 * Projekt (§2.2) und Standort — org-weite Personaldaten, daher wie die
 * Org-Sicht über `report.presence.organization` (bzw. Admin) geschützt.
 */
class PlanIstReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly PlanIstReportBuilder $builder) {}

    public function presence(Request $request): View {
        /** @var User $viewer */
        $viewer = Auth::user();

        // Drilldown (Rang 38): Team-/Org-Berechtigte dürfen die Personen-Sicht
        // anderer Mitarbeitender öffnen — immer org-gescopt aufgelöst.
        $user = $viewer;
        if ($request->filled('user')) {
            $targetId = app(SqidEncoder::class)->decode(User::class, (string) $request->query('user'));
            // Mandantengrenze: User-by-id IMMER org-scopen (User hat keinen
            // globalen OrganizationScope) — sonst wäre die Personen-Sicht
            // fremder Organisationen adressierbar (Tenant-Leak, Bauturbo A17).
            $target = $targetId !== null
                ? User::query()
                    ->where('organization_id', $viewer->organization_id)
                    ->whereKey($targetId)
                    ->first()
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

        // Mandantengrenze: "Org-Sicht" heißt EIGENE Organisation — ohne
        // expliziten Org-Filter erschienen User ALLER Organisationen
        // (Tenant-Leak, Bauturbo A17).
        $users = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->orderBy('name')
            ->get();
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

    /**
     * Schicht-Dimension (§2.3, MVP-333): Soll/Ist je Schichttyp mit Tages-/
     * Wochen-Buckets für den Verlauf.
     */
    public function shifts(Request $request): View {
        $this->authorizeExtendedDimensions();

        [$from, $to] = $this->range($request);
        $group = (string) $request->query('group', 'day');
        if (! in_array($group, ['day', 'week'], true)) {
            $group = 'day';
        }

        $report = $this->builder->shiftFor($from, $to, $group);

        return view('reports.plan-ist.shifts', [
            'report' => $report,
            'group' => $group,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Projekt-Dimension (§2.2, MVP-333): Soll aus geplanten Auftragsminuten, Ist aus Zeitbuchungen. */
    public function projects(Request $request): View {
        $this->authorizeExtendedDimensions();

        [$from, $to] = $this->range($request);
        $report = $this->builder->projectTimeFor($from, $to);

        return view('reports.plan-ist.projects', [
            'rows' => $this->paginateRows($request, $report['rows']),
            'allRows' => $report['rows'],
            'totals' => $report['totals'],
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Standort-Dimension (MVP-333): Ist-Verteilung ortsbasiert erfasster Zeiten (bewusst ohne Solldaten). */
    public function sites(Request $request): View {
        $this->authorizeExtendedDimensions();

        [$from, $to] = $this->range($request);
        $report = $this->builder->siteFor($from, $to);

        return view('reports.plan-ist.sites', [
            'rows' => $this->paginateRows($request, $report['rows']),
            'totals' => $report['totals'],
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Schicht-/Projekt-/Standort-Sicht zeigen org-weite Personal- bzw.
     * Projektdaten → Bestandsrecht der Org-Sicht (`report.presence.organization`).
     */
    private function authorizeExtendedDimensions(): void {
        /** @var User $viewer */
        $viewer = Auth::user();
        abort_unless($viewer->isAdmin() || $viewer->can(Permission::ReportPresenceOrganization->value), 403);
    }

    /**
     * Aggregat-Zeilen (Arrays) seitenweise ausliefern — Muster x-pagination standing.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateRows(Request $request, array $rows, int $perPage = 50): LengthAwarePaginator {
        $page = max(1, (int) $request->query('page', '1'));

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
        );
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

    /**
     * Zeitraum des Requests; Default ist der laufende Monat.
     *
     * Auswertung über {@see ResolvesGlobalDateRange::resolveRangeWithDefault()}
     * (Audit 2026-08, W2.1): einheitlicher Guard gegen hand-editierte
     * Bookmarks (vorher HTTP 500) und verdrehte Grenzen — der fachliche
     * Monats-Default bleibt.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array {
        // Whitebox-Konvention: "heute" lokal über Tz::current bestimmen, nicht
        // in Server-/UTC-Zeit (Monatsgrenzen kippen sonst je nach Zeitzone).
        $now = CarbonImmutable::now(Tz::current());

        return $this->resolveRangeWithDefault($request, static fn (): array => [$now->startOfMonth(), $now->endOfMonth()]);
    }
}
