<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Approval\ApprovalResponsibility;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Support\{ErrorText, MorphMap, Sqid};
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Genehmigungs-Inbox (Feature 065, MVP-154): offene Approval-Schritte
 * (decision null ODER question), je approvable nur der NIEDRIGSTE offene
 * Schritt, Zuständigkeit über {@see ApprovalResponsibility}. Welche
 * Gegenstände erscheinen, melden die Module über {@see ApprovalInboxSubject}
 * an (Service-Requests, Changes, Vertragsverhandlungen); entschieden wird
 * auf dem Weg des Gegenstands. Es zählt nur die geltende (höchste) Runde.
 */
class ApprovalInboxController extends Controller {
    /** @var array<class-string, ApprovalInboxSubject>|null */
    private ?array $subjects = null;

    public function __construct(
        private readonly ApprovalResponsibility $responsibility,
        private readonly ModuleRegistry $modules,
    ) {}

    public function index(): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->approver();
        $mine = $this->openStepsFor($user);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $items = new EloquentCollection($mine->forPage($page, $perPage)->values()->all());
        $this->preload($items);

        $approvals = new LengthAwarePaginator(
            $items,
            $mine->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('helpdesk.approvals.index', [
            'approvals' => $approvals,
            'entries' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->entryFor($a, $user)])->all(),
            'kinds' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->responsibility->kindOf($a)])->all(),
            'mappedRoles' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->responsibility->mappedRole($a)])->all(),
        ]);
    }

    public function decideForm(Approval $approval): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->approver();
        abort_unless($this->subjectFor($approval) !== null && $this->responsibility->isResponsible($approval, $user), 403);
        $this->preload(new EloquentCollection([$approval]));

        return view('helpdesk.approvals._decide_dialog', [
            'approval' => $approval,
            'entry' => $this->entryFor($approval, $user),
            'orgUsers' => User::query()
                ->where('organization_id', (int) $user->organization_id)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function decide(Request $request, Approval $approval): RedirectResponse {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->approver();
        $subject = $this->subjectFor($approval);
        abort_unless($subject !== null && $this->responsibility->isResponsible($approval, $user), 403);

        if (! $this->isLowestOpenStep($approval)) {
            return back()->with('error', __('Erst müssen die vorgelagerten Schritte entschieden werden.'));
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,question,delegated'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected,delegated'],
            'delegate' => ['nullable', 'string', 'required_if:decision,delegated'],
        ]);

        $delegateId = null;
        if ($data['decision'] === 'delegated') {
            $delegateId = Sqid::decode(User::class, $data['delegate'] ?? null);
            $exists = $delegateId !== null && User::query()
                ->whereKey($delegateId)
                ->where('organization_id', (int) $user->organization_id)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'delegate' => (string) __('Bitte einen Benutzer der eigenen Organisation wählen.'),
                ]);
            }
        }

        try {
            $subject->decide($approval, $user, $data['decision'], $data['reason'] ?? null, $delegateId);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('servicedesk.approvals.index')
            ->with('success', match ($data['decision']) {
                'approved' => __('Schritt genehmigt.'),
                'rejected' => __('Schritt abgelehnt.'),
                'question' => __('Rückfrage vermerkt — der Schritt bleibt offen.'),
                default => __('Schritt delegiert.'),
            });
    }

    /**
     * Offene Schritte (decision null ODER question) angemeldeter Gegenstände,
     * je approvable nur der niedrigste offene Step, gefiltert auf die
     * Zuständigkeit des Actors und auf Gegenstände, die noch entscheiden lassen.
     *
     * @return Collection<int, Approval>
     */
    private function openStepsFor(User $user): Collection {
        $subjects = $this->subjects();
        $steps = Approval::query()
            ->currentRound()
            ->whereIn('approvable_type', array_map(MorphMap::alias(...), array_keys($subjects)))
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            // Nach einer Ablehnung ist die Kette beendet — ihre offenen Stufen stehen nicht mehr zur Entscheidung.
            ->whereNotExists(fn($q) => $q->from('approvals as rejected')
                ->whereColumn('rejected.approvable_type', 'approvals.approvable_type')
                ->whereColumn('rejected.approvable_id', 'approvals.approvable_id')
                ->whereColumn('rejected.round', 'approvals.round')
                ->where('rejected.decision', 'rejected'))
            ->orderBy('step')
            ->orderBy('id')
            ->get()
            ->groupBy(fn(Approval $a): string => $a->approvable_type . ':' . $a->approvable_id)
            ->flatMap(function (Collection $steps): Collection {
                $lowest = (int) $steps->min('step');

                return $steps->filter(fn(Approval $a): bool => (int) $a->step === $lowest);
            })
            ->filter(fn(Approval $a): bool => $this->responsibility->isResponsible($a, $user))
            ->values();

        $steps = new EloquentCollection($steps->all());
        $steps->load('approvable');

        return $steps->toBase()
            ->filter(fn(Approval $a): bool => $a->approvable !== null && (bool) $this->subjectFor($a)?->awaitsDecision($a->approvable))
            ->values();
    }

    /** question-Schritte zählen nicht als erledigt — nur echte Entscheide. */
    private function isLowestOpenStep(Approval $approval): bool {
        return ! Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('round', $approval->round)
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->where('step', '<', $approval->step)
            ->exists();
    }

    /** @param EloquentCollection<int, Approval> $approvals */
    private function preload(EloquentCollection $approvals): void {
        $approvals->loadMissing('approvable');
        $approvals->loadMorph('approvable', array_map(
            static fn(ApprovalInboxSubject $subject): array => $subject->eagerLoad(),
            $this->subjects(),
        ));
    }

    private function entryFor(Approval $approval, User $viewer): ApprovalInboxEntry {
        $approvable = $approval->approvable;
        $subject = $this->subjectFor($approval);

        return $approvable === null || $subject === null
            ? new ApprovalInboxEntry('—')
            : $subject->present($approvable, $viewer);
    }

    private function subjectFor(Approval $approval): ?ApprovalInboxSubject {
        $class = MorphMap::classFor($approval->approvable_type);

        return $class === null ? null : ($this->subjects()[$class] ?? null);
    }

    /** @return array<class-string, ApprovalInboxSubject> Modellklasse → angemeldeter Gegenstand */
    private function subjects(): array {
        if ($this->subjects === null) {
            $this->subjects = [];
            foreach ($this->modules->extensions(ApprovalInboxSubject::class) as $class) {
                /** @var ApprovalInboxSubject $subject */
                $subject = app($class);
                $this->subjects[$subject->approvableClass()] = $subject;
            }
        }

        return $this->subjects;
    }

    private function approver(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
