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
use App\Models\{Approval, Change, ServiceRequest, User};
use App\Services\ServiceTicket\{ChangeService, ServiceRequestService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Genehmigungs-Inbox (Feature 065, MVP-154): offene Approval-Schritte
 * (decision null ODER question), je approvable nur der NIEDRIGSTE offene
 * Schritt, Zuständigkeit über approver_rule (user-Id bzw. Rolle). Zeigt
 * ServiceRequests UND Changes (eine Mechanik, MVP-157 nutzt sie mit) —
 * decide() verzweigt nach approvable_type auf den Domänen-Service.
 */
class ApprovalInboxController extends Controller {
    public function index(): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $mine = $this->openStepsFor($this->approver());

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $items = new \Illuminate\Database\Eloquent\Collection($mine->forPage($page, $perPage)->values()->all());
        $items->load('approvable');

        $approvals = new LengthAwarePaginator(
            $items,
            $mine->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('helpdesk.approvals.index', [
            'approvals' => $approvals,
        ]);
    }

    public function decideForm(Approval $approval): View {
        Gate::authorize(Permission::ServiceRequestApprove->value);

        $user = $this->approver();
        abort_unless($this->isResponsible($approval, $user), 403);

        return view('helpdesk.approvals._decide_dialog', [
            'approval' => $approval->loadMissing('approvable'),
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
        abort_unless($this->isResponsible($approval, $user), 403);

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
            match ($approval->approvable_type) {
                ServiceRequest::class => app(ServiceRequestService::class)
                    ->decide($approval, $user, $data['decision'], $data['reason'] ?? null, $delegateId),
                Change::class => app(ChangeService::class)
                    ->decide($approval, $user, $data['decision'], $data['reason'] ?? null, $delegateId),
                default => abort(422, (string) __('Unbekannter Genehmigungsgegenstand.')),
            };
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
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
     * Offene Schritte (decision null ODER question), je approvable nur der
     * niedrigste offene Step, gefiltert auf die Zuständigkeit des Actors.
     *
     * @return Collection<int, Approval>
     */
    private function openStepsFor(User $user): Collection {
        return Approval::query()
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderBy('step')
            ->orderBy('id')
            ->get()
            ->groupBy(fn(Approval $a): string => $a->approvable_type . ':' . $a->approvable_id)
            ->flatMap(function (Collection $steps): Collection {
                $lowest = (int) $steps->min('step');

                return $steps->filter(fn(Approval $a): bool => (int) $a->step === $lowest);
            })
            ->filter(fn(Approval $a): bool => $this->isResponsible($a, $user))
            ->values();
    }

    /** Zuständigkeit laut approver_rule: user → Id-Match, role → hasRole. */
    private function isResponsible(Approval $approval, User $user): bool {
        $rule = (array) $approval->approver_rule;

        return match ((string) ($rule['type'] ?? '')) {
            'user' => (int) ($rule['value'] ?? 0) === (int) $user->id,
            'role' => $user->hasRole((string) ($rule['value'] ?? '')),
            default => false,
        };
    }

    /** question-Schritte zählen nicht als erledigt — nur echte Entscheide. */
    private function isLowestOpenStep(Approval $approval): bool {
        return ! Approval::query()
            ->where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->where('step', '<', $approval->step)
            ->exists();
    }

    private function approver(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
