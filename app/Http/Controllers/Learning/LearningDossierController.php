<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Access\AccessMedium;
use App\Models\Audit\AuditLog;
use App\Models\Facility\Site;
use App\Models\Platform\{Organization, Team, User};
use App\Models\Project\Project;
use App\Models\Time\{Attendance, AttendanceCheckpoint};
use App\Services\Learning\{LearningDossierArchive, LearningDossierPdfRenderer, QualificationDossierService};
use App\Support\{MorphMap, Sqid};
use App\Support\Query\DateRange;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nachweismappe (Feature 149, MVP-750): „war diese Person **am 14. März**
 * unterwiesen?" — nicht „ist sie es heute?".
 *
 * **Aggregiert ist die Vorgabe.** Die namentliche Ausprägung ist eine
 * Weitergabe personenbezogener Daten und muss deshalb ausdrücklich
 * angefordert werden; der Anlass wird mitgeschrieben. Wer sie ohne Anlass
 * anfordert, bekommt sie nicht.
 */
class LearningDossierController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly QualificationDossierService $dossier,
        private readonly LearningDossierPdfRenderer $pdf,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::LearningManage->value);

        [$from, $to] = $this->period();
        $selection = $this->selection($request);
        $users = $this->users($selection, $from, $to);
        $named = $this->namedRequested($request);

        $rows = $named ? $this->dossier->forUsers($users, $from, $to) : [];

        if ($named) {
            $this->recordNamedAccess($request, $users->count(), $from, $to);
        }

        return view('learning.dossier.index', [
            'from' => $from,
            'to' => $to,
            'teams' => Team::query()->orderBy('name')->get(),
            'teamSqid' => $selection['team']?->sqid,
            // Auswahl nach Person, Projekt, Standort (MVP-1600).
            'people' => $named ? $this->activeOn($from)->get(['id', 'name', 'deactivated_at']) : collect(),
            'personSqid' => $selection['user']?->sqid,
            'projects' => $this->visibleProjects()->orderBy('name')->get(['id', 'name', 'number']),
            'projectSqid' => $selection['project']?->sqid,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'siteSqid' => $selection['site']?->sqid,
            'users' => $users,
            'named' => $named,
            'reason' => (string) $request->string('reason'),
            'rows' => $rows,
            'summary' => $this->dossier->coverageSummary($users, $from, $to),
        ]);
    }

    /** Die Mappe als PDF — trägt Zeitraum, Anlass und den Prüfhash. */
    public function pdf(Request $request): Response {
        Gate::authorize(Permission::LearningManage->value);

        [$from, $to] = $this->period();
        $users = $this->users($this->selection($request), $from, $to);
        $named = $this->namedRequested($request);

        if ($named) {
            $this->recordNamedAccess($request, $users->count(), $from, $to);
        }

        $content = $this->pdf->output(
            $this->currentOrganization(),
            $users,
            $from,
            $named,
            (string) $request->string('reason'),
            $to,
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nachweismappe-' . $from->toDateString() . '.pdf"',
        ]);
    }

    /**
     * Maschinenlesbar für Auditoren — reproduzierbar über den Hash. Das Format
     * ist die namentliche Mappe (eine aggregierte Variante gibt es nicht),
     * also nur mit Anlass; die Seite zeigt den Knopf erst dann.
     */
    public function json(Request $request): StreamedResponse {
        Gate::authorize(Permission::LearningManage->value);
        abort_unless($this->namedRequested($request), 403);

        [$from, $to] = $this->period();
        $users = $this->users($this->selection($request), $from, $to);

        $this->recordNamedAccess($request, $users->count(), $from, $to);

        $payload = $this->dossier->exportPayload($this->currentOrganization(), $users, $from, $to);

        return response()->streamDownload(
            static function () use ($payload): void {
                echo JsonHelper::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            },
            'nachweismappe-' . $from->toDateString() . '.json',
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Mappe als ZIP (MVP-1600): PDF, JSON, die Zertifikate als Einzelbelege
     * und ein Index. Namentlich wie das JSON — also nur mit Anlass.
     */
    public function zip(Request $request, LearningDossierArchive $archive): Response {
        Gate::authorize(Permission::LearningManage->value);
        abort_unless($this->namedRequested($request), 403);

        [$from, $to] = $this->period();
        $users = $this->users($this->selection($request), $from, $to);
        $content = $archive->build($this->currentOrganization(), $users, $from, $to, trim((string) $request->string('reason')));

        $this->recordNamedAccess($request, $users->count(), $from, $to);

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="nachweismappe-' . $from->toDateString() . '.zip"',
        ]);
    }

    /**
     * Betrachteter Zeitraum — der globale Kopf-Zeitraum.
     *
     * Vorher hatte die Seite einen eigenen Stichtag und daneben den
     * wirkungslosen Kopf-Regler: zwei Datumsbedienelemente, von denen eines
     * nichts tat. Der Zeitraum kann mehr als der Stichtag, nicht weniger —
     * ein Nachweis deckt ihn ganz, teilweise oder gar nicht, und die
     * Punktaussage ist der Sonderfall `von == bis`.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(): array {
        [$from, $to] = $this->globalDateRangeBounds();

        return [Carbon::parse($from->toDateString())->startOfDay(), Carbon::parse($to->toDateString())->startOfDay()];
    }

    /**
     * Auswahl der Mappe: Team, einzelne Person, Projekt, Standort — kombinierbar.
     *
     * @return array{team: Team|null, user: User|null, project: Project|null, site: Site|null}
     */
    private function selection(Request $request): array {
        $teamId = Sqid::decode(Team::class, (string) $request->string('team_id'));
        $userId = Sqid::decode(User::class, (string) $request->string('user_id'));
        $projectId = Sqid::decode(Project::class, (string) $request->string('project_id'));
        $siteId = Sqid::decode(Site::class, (string) $request->string('site_id'));

        return [
            'team' => $teamId !== null ? Team::query()->find($teamId) : null,
            // Eine einzelne Person ist immer eine namentliche Auskunft — ohne Anlass bleibt die Auswahl wirkungslos.
            'user' => $userId !== null && $this->namedRequested($request) ? User::query()->inCurrentOrganization()->find($userId) : null,
            'project' => $projectId !== null ? $this->visibleProjects()->find($projectId) : null,
            'site' => $siteId !== null ? Site::query()->find($siteId) : null,
        ];
    }

    /**
     * Projekte zur Auswahl: alle der Organisation mit Projektleserecht, sonst
     * nur die, an denen die anfragende Person selbst beteiligt ist.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Project>
     */
    private function visibleProjects(): \Illuminate\Database\Eloquent\Builder {
        $query = Project::query();

        return Gate::allows('viewAny', Project::class) ? $query : $query->forUser($this->authUser());
    }

    /**
     * Personen der eigenen Organisation, die zu Beginn des Zeitraums beschäftigt
     * waren. Wer danach ausgeschieden ist, gehört in die Mappe: gefragt ist der
     * Unterweisungsstand am Stichtag, nicht der heutige Personalstand.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function activeOn(Carbon $from): \Illuminate\Database\Eloquent\Builder {
        return User::query()
            ->inCurrentOrganization()
            ->where(fn ($query) => $query->whereNull('deactivated_at')->orWhere('deactivated_at', '>', $from))
            ->orderBy('name');
    }

    /**
     * @param  array{team: Team|null, user: User|null, project: Project|null, site: Site|null}  $selection
     * @return EloquentCollection<int, User>
     */
    private function users(array $selection, Carbon $from, Carbon $to): EloquentCollection {
        $query = $this->activeOn($from);

        if ($selection['team'] !== null) {
            $teamId = $selection['team']->id;
            $query->whereHas('teams', fn ($q) => $q->whereKey($teamId));
        }
        if ($selection['user'] !== null) {
            $query->whereKey($selection['user']->id);
        }
        if ($selection['project'] !== null) {
            // Einzelmitglieder und die Mitglieder der Projektteams.
            $project = $selection['project'];
            $query->where(fn ($q) => $q
                ->whereIn('users.id', $project->members()->select('users.id'))
                ->orWhereHas('teams', fn ($t) => $t->whereIn('teams.id', $project->teams()->select('teams.id'))));
        }
        if ($selection['site'] !== null) {
            // Am Standort eingesetzt: im Zeitraum an einem Kontrollpunkt des Standorts gestempelt oder ein Zutrittsmedium dafür.
            $siteId = $selection['site']->id;
            $checkpoints = AttendanceCheckpoint::query()->where('site_id', $siteId)->select('id');
            $query->where(fn ($q) => $q
                ->whereIn('users.id', Attendance::query()
                    ->whereBetween('date', DateRange::days($from, $to))
                    ->where(fn ($a) => $a->whereIn('started_checkpoint_id', $checkpoints)->orWhereIn('ended_checkpoint_id', $checkpoints))
                    ->select('user_id'))
                ->orWhereIn('users.id', AccessMedium::query()
                    ->where('site_id', $siteId)
                    ->whereNotNull('holder_user_id')
                    ->select('holder_user_id')));
        }

        return $query->get();
    }

    /** Namentlich nur mit Anlass — ohne Begründung bleibt es aggregiert. */
    private function namedRequested(Request $request): bool {
        return $request->boolean('named') && trim((string) $request->string('reason')) !== '';
    }

    /**
     * Eine namentliche Auskunft ist eine Weitergabe personenbezogener Daten
     * — wer sie wann und **warum** angefordert hat, gehört ins Protokoll.
     */
    private function recordNamedAccess(Request $request, int $people, Carbon $from, Carbon $to): void {
        $organization = $this->currentOrganization();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => Auth::id(),
            'event' => 'learning.dossierDisclosed',
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => [
                'reason' => trim((string) $request->string('reason')),
                'people' => $people,
                // Zeitraum statt Stichtag: das Protokoll muss festhalten,
                // WORUEBER Auskunft gegeben wurde, nicht nur wann.
                'as_of' => $from->toDateString(),
                'as_of_to' => $to->toDateString(),
            ],
        ]);
    }
}
