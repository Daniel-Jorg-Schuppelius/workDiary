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

use App\Enums\Whistleblowing\{CaseRole, CaseStatus};
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\{
    WhistleblowingAccessService,
    WhistleblowingAssignmentService,
    WhistleblowingCaseWorkflowService,
    WhistleblowingDeletionService,
    WhistleblowingEventService,
    WhistleblowingExportService,
    WhistleblowingMessageService,
};
use Illuminate\Http\{RedirectResponse, Request};
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Interne Fallbearbeitung (Abschnitt 7.3 / 13.2). Jeder Zugriff wird ueber die
 * {@see \App\Policies\WhistleblowingCasePolicy} autorisiert (Permission UND
 * Fall-Zuweisung). Die Liste zeigt KEINE Inhaltsvorschau.
 */
class InternalCaseController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', WhistleblowingCase::class);

        // Bewusst ohne ciphertext-Spalten: keine Inhaltsvorschau in der Liste.
        $cases = WhistleblowingCase::query()
            ->select(['id', 'public_id', 'case_number', 'category', 'status', 'priority',
                'acknowledgement_due_at', 'feedback_due_at', 'created_at'])
            ->latest()
            ->paginate(25);

        return view('whistleblowing.internal.index', ['cases' => $cases]);
    }

    public function show(WhistleblowingCase $case): View {
        Gate::authorize('view', $case);
        $this->event($case)->record($case, WhistleblowingEventService::CASE_VIEWED, $this->user());

        $case->load(['assignments.user', 'messages']);

        return view('whistleblowing.internal.show', ['case' => $case]);
    }

    public function acknowledge(WhistleblowingCase $case, WhistleblowingCaseWorkflowService $workflow): RedirectResponse {
        Gate::authorize('process', $case);
        $workflow->acknowledge($case, $this->user());

        return back()->with('success', __('Eingang bestätigt.'));
    }

    public function status(Request $request, WhistleblowingCase $case, WhistleblowingCaseWorkflowService $workflow): RedirectResponse {
        Gate::authorize('process', $case);
        $data = $request->validate([
            'to' => ['required', Rule::in(array_column(CaseStatus::cases(), 'value'))],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $workflow->transition($case, CaseStatus::from($data['to']), $this->user(), $data['reason'] ?? null);

        return back()->with('success', __('Status aktualisiert.'));
    }

    public function assign(Request $request, WhistleblowingCase $case, WhistleblowingAssignmentService $assignments): RedirectResponse {
        Gate::authorize('assign', $case);
        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'role' => ['required', Rule::in(array_column(CaseRole::cases(), 'value'))],
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        $assignments->assign($case, $user, CaseRole::from($data['role']), $this->user());

        return back()->with('success', __('Bearbeiter zugewiesen.'));
    }

    public function note(Request $request, WhistleblowingCase $case, WhistleblowingMessageService $messages): RedirectResponse {
        Gate::authorize('note', $case);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $messages->addInternalNote($case, $data['body'], $this->user());

        return back()->with('success', __('Notiz gespeichert.'));
    }

    public function message(Request $request, WhistleblowingCase $case, WhistleblowingMessageService $messages): RedirectResponse {
        Gate::authorize('message', $case);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $messages->sendToReporter($case, $data['body'], $this->user());

        return back()->with('success', __('Nachricht an die meldende Person gesendet.'));
    }

    public function conflict(Request $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('declareConflict', $case);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $access->declareConflict($case, $this->user(), $data['reason'] ?? null);

        return redirect()
            ->route('whistleblowing.internal.index')
            ->with('success', __('Sie haben sich wegen Interessenkonflikts gesperrt.'));
    }

    public function emergency(Request $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('grantEmergency', $case);
        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        /** @var User $grantee */
        $grantee = User::query()->findOrFail($data['user_id']);
        $access->grantEmergencyAccess($case, $grantee, $this->user(), $data['reason']);

        return back()->with('success', __('Notfallfreigabe erteilt.'));
    }

    public function subject(Request $request, WhistleblowingCase $case, WhistleblowingAccessService $access): RedirectResponse {
        Gate::authorize('process', $case);
        $data = $request->validate([
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        $access->markSubject($case, $user, $this->user(), $data['note'] ?? null);

        return back()->with('success', __('Betroffene Person markiert (für den Fall gesperrt).'));
    }

    public function export(Request $request, WhistleblowingCase $case, WhistleblowingExportService $export): BinaryFileResponse {
        Gate::authorize('export', $case);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $result = $export->export($case, $data['reason'], $this->user());

        return response()
            ->download($result['path'], $result['filename'], [
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    public function destroy(WhistleblowingCase $case, WhistleblowingDeletionService $deletion): RedirectResponse {
        Gate::authorize('retention', $case);
        $deletion->delete($case, $this->user());

        return redirect()
            ->route('whistleblowing.internal.index')
            ->with('success', __('Fall kontrolliert gelöscht (Crypto-Shredding).'));
    }

    private function user(): User {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function event(WhistleblowingCase $case): WhistleblowingEventService {
        return app(WhistleblowingEventService::class);
    }
}
