<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\DataSubjectRequestType;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Services\Privacy\{DataSubjectRequestService, PrivacyExportService};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Bearbeitung von Betroffenenanfragen (DSGVO Art. 15–21). Alle Aktionen sind
 * organisationsgebunden und ueber die {@see \App\Policies\Privacy\DataSubjectRequestPolicy}
 * abgesichert; das harte Modul-Gate (EnforcePlanModules) sperrt zusaetzlich.
 */
class DataSubjectRequestController extends Controller {
    public function __construct(
        private readonly DataSubjectRequestService $service,
        private readonly PrivacyExportService $exporter,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', DataSubjectRequest::class);

        $requests = DataSubjectRequest::query()
            ->latest('received_at')
            ->paginate(20);

        return view('privacy.requests.index', ['requests' => $requests]);
    }

    public function create(): View {
        Gate::authorize('create', DataSubjectRequest::class);

        return view('privacy.requests._form_dialog', ['types' => DataSubjectRequestType::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', DataSubjectRequest::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:20000'],
            'channel' => ['nullable', 'string', 'max:32'],
        ]);

        $dsr = $this->service->open(
            $org,
            DataSubjectRequestType::from($data['type']),
            $data['subject'],
            $data['content'],
            $data['channel'] ?? null,
            $request->user(),
        );

        return redirect()->route('dataprotection.requests.show', $dsr)
            ->with('status', __('Betroffenenanfrage angelegt.'));
    }

    public function show(DataSubjectRequest $request): View {
        Gate::authorize('view', $request);

        return view('privacy.requests.show', [
            'request' => $request->load('assignedUser'),
            'events' => $request->events()->get(),
            'members' => User::query()
                ->where('organization_id', $request->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function verifyIdentity(DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('update', $dsr);
        $this->service->verifyIdentity($dsr, Auth::user());

        return back()->with('status', __('Identität bestätigt.'));
    }

    public function assign(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('assign', $dsr);
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $assignee = User::query()
            ->where('organization_id', $dsr->organization_id)
            ->findOrFail((int) $data['user_id']);
        $this->service->assign($dsr, $assignee, $request->user());

        return back()->with('status', __('Anfrage zugewiesen.'));
    }

    public function decide(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('update', $dsr);
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:granted,partially,rejected'],
            'note' => ['required', 'string', 'min:5', 'max:20000'],
        ]);

        $this->service->decide($dsr, $data['decision'], $data['note'], $request->user());

        return redirect()->route('dataprotection.requests.show', $dsr)
            ->with('status', __('Entscheidung dokumentiert.'));
    }

    public function export(Request $request, DataSubjectRequest $dsr): Response {
        Gate::authorize('export', $dsr);

        $payload = $this->exporter->requestExport($dsr);
        $this->audit($request, 'privacy.dsr.exported', DataSubjectRequest::class, (int) $dsr->id);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response((string) $json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $dsr->request_number . '.json"',
        ]);
    }

    private function audit(Request $request, string $event, string $type, int $id): void {
        $org = $request->user()?->organization;
        AuditLog::create([
            'organization_id' => $org?->id,
            'user_id' => $request->user()?->id,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'changes' => [],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
