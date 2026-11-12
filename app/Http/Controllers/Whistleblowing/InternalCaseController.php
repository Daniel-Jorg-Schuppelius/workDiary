<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InternalCaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Enums\Whistleblowing\{CaseCategory, CasePriority, CaseRole, CaseStatus};
use App\Http\Controllers\Controller;
use App\Http\Requests\Whistleblowing\{AssignWhistleblowingCaseRequest, DeclareWhistleblowingConflictRequest, ExportWhistleblowingCaseRequest, GrantWhistleblowingEmergencyAccessRequest, MarkWhistleblowingSubjectRequest, StoreWhistleblowingMessageRequest, UpdateWhistleblowingCaseStatusRequest};
use App\Models\Platform\User;
use App\Models\Whistleblowing\{CaseAssignment, CaseEvent, WhistleblowingCase};
use App\Services\Whistleblowing\{
    WhistleblowingAccessService,
    WhistleblowingAssignmentService,
    WhistleblowingAttachmentService,
    WhistleblowingCaseWorkflowService,
    WhistleblowingDeletionService,
    WhistleblowingEventService,
    WhistleblowingExportService,
    WhistleblowingMessageService,
};
use App\Services\Whistleblowing\InvalidCaseTransition;
use App\Support\ErrorText;
use App\Support\Query\DateRange;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Interne Fallbearbeitung (Abschnitt 7.3 / 13.2). Jeder Zugriff wird ueber die
 * {@see \App\Policies\Whistleblowing\WhistleblowingCasePolicy} autorisiert (Permission UND
 * Fall-Zuweisung). Die Liste zeigt KEINE Inhaltsvorschau.
 */
class InternalCaseController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', WhistleblowingCase::class);

        $user = $this->authUser();
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CaseStatus::class)],
            'category' => ['nullable', Rule::enum(CaseCategory::class)],
            'priority' => ['nullable', Rule::enum(CasePriority::class)],
            'assignee' => ['nullable', 'string', 'max:64'],
            'deadline' => ['nullable', Rule::in(['overdue', 'soon', 'feedback_open'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // Bewusst ohne ciphertext-Spalten: keine Inhaltsvorschau in der Liste.
        $query = WhistleblowingCase::query()
            ->select(['id', 'organization_id', 'public_id', 'case_number', 'category', 'status', 'priority',
                'acknowledgement_due_at', 'feedback_due_at', 'acknowledged_at', 'feedback_sent_at', 'created_at'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['assignee'] ?? null, fn ($q, $assignee) => $q->whereHas(
                'assignments',
                // Unbekannte Kennung ergibt keine Treffer statt aller.
                fn ($a) => $a->where('user_id', (int) ((new User)->resolveRouteBinding($assignee)?->getKey() ?? 0))->whereNull('revoked_at'),
            ))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', DateRange::dayStart($from)))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', DateRange::dayAfter($to)));

        // Kategorie und Priorität sind erst nach Zuweisung sichtbar (MVP-802). Der Filter
        // darf sie nicht über die Trefferliste verraten: Er greift nur in eigenen Fällen.
        if (($filters['category'] ?? null) !== null || ($filters['priority'] ?? null) !== null) {
            $query
                ->whereHas('assignments', fn ($a) => $a->where('user_id', $user->getKey())->whereNull('revoked_at'))
                ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
                ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority));
        }

        $open = fn ($q) => $q->whereNotIn('status', array_map(
            static fn (CaseStatus $status): string => $status->value,
            array_filter(CaseStatus::cases(), static fn (CaseStatus $status): bool => $status->isClosed()
                || in_array($status, [CaseStatus::RetentionReview, CaseStatus::LegalHold, CaseStatus::Deleted], true)),
        ));
        $pending = fn ($q, $until) => $q->where(fn ($w) => $w
            ->where(fn ($ack) => $ack->whereNull('acknowledged_at')->where('status', CaseStatus::Submitted->value)->where('acknowledgement_due_at', '<=', $until))
            ->orWhere(fn ($fb) => $fb->whereNull('feedback_sent_at')->where('feedback_due_at', '<=', $until)));
        match ($filters['deadline'] ?? null) {
            'overdue' => $pending($open($query), now()),
            'soon' => $pending($open($query), now()->addDays(14)),
            'feedback_open' => $open($query)->whereNull('feedback_sent_at'),
            default => null,
        };

        $cases = $query->latest()->paginate(25)->withQueryString();

        // Kategorie und Priorität erst nach Zuweisung (Entscheid P13-26, MVP-802):
        // In kleinen Organisationen erlauben sie sonst einen Rückschluss auf die
        // meldende Person. Maßgeblich ist dieselbe Regel wie für den Fall selbst.
        $metadataVisible = $cases->getCollection()
            ->filter(fn (WhistleblowingCase $case): bool => $user->can('view', $case))
            ->map(fn (WhistleblowingCase $case): int => (int) $case->id)
            ->values()
            ->all();

        $assignees = User::query()
            ->whereIn('id', CaseAssignment::query()->whereNull('revoked_at')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('whistleblowing.internal.index', [
            'cases' => $cases,
            'metadataVisible' => $metadataVisible,
            'filters' => $filters,
            'assignees' => $assignees,
        ]);
    }

    public function show(WhistleblowingCase $case): View {
        Gate::authorize('view', $case);
        $this->event($case)->record($case, WhistleblowingEventService::CASE_VIEWED, $this->authUser());

        $case->load(['assignments.user', 'messages', 'attachments']);

        return view('whistleblowing.internal.show', ['case' => $case]);
    }

    public function acknowledge(WhistleblowingCase $case, WhistleblowingCaseWorkflowService $workflow): RedirectResponse {
        Gate::authorize('process', $case);
        $workflow->acknowledge($case, $this->authUser());

        return back()->with('success', __('Eingang bestätigt.'));
    }

    public function status(UpdateWhistleblowingCaseStatusRequest $request, WhistleblowingCase $case, WhistleblowingCaseWorkflowService $workflow): RedirectResponse {
        Gate::authorize('process', $case);
        $data = $request->validated();

        // Der Stand kann sich nach dem Laden der Seite geändert haben — dann Meldung statt HTTP 500.
        try {
            $workflow->transition($case, CaseStatus::from($data['to']), $this->authUser(), $data['reason'] ?? null, [
                'basis' => $data['legal_hold_basis'] ?? null,
                'responsible' => $data['legal_hold_responsible'] ?? null,
                'review_at' => isset($data['legal_hold_review_at']) ? Carbon::parse((string) $data['legal_hold_review_at']) : null,
            ]);
        } catch (InvalidCaseTransition) {
            return back()->withInput()->withErrors(['to' => __('Dieser Statuswechsel ist aus dem aktuellen Stand nicht möglich.')]);
        }

        return back()->with('success', __('Status aktualisiert.'));
    }

    public function assign(AssignWhistleblowingCaseRequest $request, WhistleblowingCase $case, WhistleblowingAssignmentService $assignments): RedirectResponse {
        Gate::authorize('assign', $case);
        $data = $request->validated();

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        $assignments->assign($case, $user, CaseRole::from($data['role']), $this->authUser());

        return back()->with('success', __('Bearbeiter zugewiesen.'));
    }

    public function revoke(AssignWhistleblowingCaseRequest $request, WhistleblowingCase $case, WhistleblowingAssignmentService $assignments): RedirectResponse {
        Gate::authorize('assign', $case);
        $data = $request->validated();

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        $assignments->revoke($case, $user, CaseRole::from($data['role']), $this->authUser());

        return back()->with('success', __('Zuweisung widerrufen.'));
    }

    /** Unterlage der Meldestelle am Fall; liegt wie jeder Anhang bis zum Scan in Quarantäne. */
    public function attach(Request $request, WhistleblowingCase $case, WhistleblowingAttachmentService $attachments): RedirectResponse {
        Gate::authorize('process', $case);
        $allowed = (array) config('whistleblowing.uploads.allowed_mimes', []);
        $maxKb = (int) ceil(((int) config('whistleblowing.uploads.max_bytes', 26214400)) / 1024);
        $request->validate(['file' => ['required', File::types($allowed)->max($maxKb)]]);

        try {
            $attachments->storeHandlerUpload($case, $request->file('file'), $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('Anhang hochgeladen. Er ist abrufbar, sobald die Prüfung ihn freigegeben hat.'));
    }

    /** Ereignisprotokoll des Falls (inhaltsfrei). Der Aufruf steht selbst im Protokoll. */
    public function events(WhistleblowingCase $case): View {
        Gate::authorize('audit', $case);
        $this->event($case)->record($case, WhistleblowingEventService::CASE_AUDIT_VIEWED, $this->authUser());

        $caseId = (int) $case->getKey();
        $events = CaseEvent::query()
            ->where('organization_id', $case->getAttribute('organization_id'))
            ->where(fn ($q) => $q
                ->where('case_id', $caseId)
                // Nachrichten aus dem Postfach schreibt das System ohne Fallbezug in der Spalte.
                ->orWhere(fn ($w) => $w->whereNull('case_id')->where('metadata->case_id', $caseId)))
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->paginate(50);

        $userIds = $events->getCollection()
            ->flatMap(fn (CaseEvent $event): array => array_values(array_intersect_key(
                $event->payloadData(),
                array_flip(['assignee_user_id', 'grantee_user_id', 'subject_user_id', 'user_id']),
            )))
            ->filter(static fn (mixed $id): bool => is_int($id))
            ->unique()
            ->all();

        return view('whistleblowing.internal.events', [
            'case' => $case,
            'events' => $events,
            'users' => User::query()->whereIn('id', $userIds)->pluck('name', 'id'),
        ]);
    }

    public function note(StoreWhistleblowingMessageRequest $request, WhistleblowingCase $case, WhistleblowingMessageService $messages): RedirectResponse {
        Gate::authorize('note', $case);
        $data = $request->validated();

        $messages->addInternalNote($case, $data['body'], $this->authUser());

        return back()->with('success', __('Notiz gespeichert.'));
    }

    public function message(StoreWhistleblowingMessageRequest $request, WhistleblowingCase $case, WhistleblowingMessageService $messages): RedirectResponse {
        Gate::authorize('message', $case);
        $data = $request->validated();

        $messages->sendToReporter($case, $data['body'], $this->authUser(), (bool) ($data['is_feedback'] ?? false));

        return back()->with('success', __('Nachricht an die meldende Person gesendet.'));
    }

    public function conflict(DeclareWhistleblowingConflictRequest $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('declareConflict', $case);
        $data = $request->validated();

        $access->declareConflict($case, $this->authUser(), $data['reason'] ?? null);

        return redirect()
            ->toList('whistleblowing.internal.index')
            ->with('success', __('Sie haben sich wegen Interessenkonflikts gesperrt.'));
    }

    public function emergency(GrantWhistleblowingEmergencyAccessRequest $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('grantEmergency', $case);
        $data = $request->validated();

        /** @var User $grantee */
        $grantee = User::query()->findOrFail($data['user_id']);
        $access->grantEmergencyAccess($case, $grantee, $this->authUser(), $data['reason']);

        return back()->with('success', __('Notfallfreigabe erteilt.'));
    }

    public function subject(MarkWhistleblowingSubjectRequest $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('process', $case);
        $data = $request->validated();

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        $access->markSubject($case, $user, $this->authUser(), $data['note'] ?? null);

        return back()->with('success', __('Betroffene Person markiert (für den Fall gesperrt).'));
    }

    public function export(ExportWhistleblowingCaseRequest $request, WhistleblowingCase $case, WhistleblowingExportService $export): BinaryFileResponse|RedirectResponse {
        Gate::authorize('export', $case);
        $data = $request->validated();

        try {
            $result = $export->export($case, $data['reason'], $this->authUser(), (string) $data['password']);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return response()
            ->download($result['path'], $result['filename'], [
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    public function destroy(WhistleblowingCase $case, WhistleblowingDeletionService $deletion): RedirectResponse {
        Gate::authorize('retention', $case);

        // Der Dienst lehnt außerhalb der Aufbewahrungsprüfung und unter Löschsperre ab —
        // auch wenn der Stand sich nach dem Laden der Seite geändert hat.
        try {
            $deletion->delete($case, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()
            ->toList('whistleblowing.internal.index')
            ->with('success', __('Fall kontrolliert gelöscht (Crypto-Shredding).'));
    }

    private function event(WhistleblowingCase $case): WhistleblowingEventService {
        return app(WhistleblowingEventService::class);
    }
}
