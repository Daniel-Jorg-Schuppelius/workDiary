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

namespace App\Http\Controllers\Approval;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Approval\Approval;
use App\Models\Platform\User;
use App\Services\Approval\{ApprovalInbox, ApprovalResponsibility};
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Support\{ErrorText, Sqid};
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Facades\Gate;
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
    public function __construct(
        private readonly ApprovalResponsibility $responsibility,
        private readonly ApprovalInbox $inbox,
    ) {}

    public function index(): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->authUser();
        $mine = $this->inbox->openStepsFor($user);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $items = new EloquentCollection($mine->forPage($page, $perPage)->values()->all());
        $this->inbox->preload($items);

        $approvals = new LengthAwarePaginator(
            $items,
            $mine->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('helpdesk.approvals.index', [
            'approvals' => $approvals,
            'entries' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->inbox->entryFor($a, $user)])->all(),
            'kinds' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->responsibility->kindOf($a)])->all(),
            'mappedRoles' => $items->mapWithKeys(fn(Approval $a): array => [$a->id => $this->responsibility->mappedRole($a)])->all(),
        ]);
    }

    public function decideForm(Approval $approval): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->authUser();
        abort_unless($this->inbox->subjectFor($approval) !== null && $this->responsibility->isResponsible($approval, $user), 403);
        $this->inbox->preload(new EloquentCollection([$approval]));

        return view('helpdesk.approvals._decide_dialog', [
            'approval' => $approval,
            'entry' => $this->inbox->entryFor($approval, $user),
            'orgUsers' => User::query()
                ->where('organization_id', (int) $user->organization_id)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function decide(Request $request, Approval $approval): RedirectResponse {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->authUser();
        $subject = $this->inbox->subjectFor($approval);
        abort_unless($subject !== null && $this->responsibility->isResponsible($approval, $user), 403);

        if (! $this->inbox->isLowestOpenStep($approval)) {
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

        return redirect()->route('approvals.index')
            ->with('success', match ($data['decision']) {
                'approved' => __('Schritt genehmigt.'),
                'rejected' => __('Schritt abgelehnt.'),
                'question' => __('Rückfrage vermerkt — der Schritt bleibt offen.'),
                default => __('Schritt delegiert.'),
            });
    }
}
