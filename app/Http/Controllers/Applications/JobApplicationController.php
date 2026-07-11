<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Enums\Document\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Applications\{EmployeeDraft, JobApplication, JobRequisition};
use App\Models\User;
use App\Services\Applications\RecruitingService;
use App\Services\Document\DocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Bewerbungsakten (Feature 068, MVP-190–193): Pipeline, Gespräche,
 * Bewertungen, Entscheidung, Datenschutz-Panel (Aufbewahrung, Auskunft,
 * Anonymisierung, Talentpool) und Onboarding-Übergabe.
 */
class JobApplicationController extends Controller {
    public function __construct(private readonly RecruitingService $recruiting) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', JobApplication::class);

        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, JobApplication::STATUSES, true) ? $status : '';

        return view('applications.recruiting.applications.index', [
            'applications' => JobApplication::query()
                ->with(['requisition', 'responsible'])
                ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'statuses' => JobApplication::STATUSES,
            'filters' => ['status' => $statusFilter],
        ]);
    }

    public function create(): View {
        Gate::authorize('create', JobApplication::class);

        return view('applications.recruiting.applications._form_dialog', [
            'requisitions' => JobRequisition::query()->whereIn('status', ['draft', 'open'])->orderBy('title')->get(['id', 'title']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', JobApplication::class);
        $request->merge([
            'job_requisition_id' => \App\Support\Sqid::decodeOrNumeric(JobRequisition::class, $request->input('job_requisition_id')),
            'responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id')),
        ]);
        $data = $request->validate([
            'job_requisition_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('job_requisitions')],
            'candidate_name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['required', 'in:website,portal,agency,social,print,referral,other'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
        ]);

        ['application' => $application, 'duplicates' => $duplicates] = $this->recruiting->intake($data, $this->actor());

        $notice = __('Bewerbung erfasst.');
        if ($duplicates > 0) {
            $notice .= ' ' . __('Achtung: :count frühere Bewerbung(en) mit derselben E-Mail (Dublettenhinweis).', ['count' => $duplicates]);
        }

        return redirect()->route('recruiting.applications.show', $application)->with('status', $notice);
    }

    public function show(JobApplication $application): View {
        Gate::authorize('view', $application);
        $application->load(['requisition', 'posting', 'documents.document', 'interviews.interviewer', 'reviews.reviewer', 'negotiations.versions', 'negotiations.reviewItems', 'negotiations.approvals', 'employeeDraft', 'responsible']);

        return view('applications.recruiting.applications.show', [
            'application' => $application,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateStatus(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('update', $application);
        $data = $request->validate([
            'status' => ['required', 'in:screened,interview_planned,interviewed,task_open'],
        ]);
        if (! in_array($application->status, JobApplication::PIPELINE_STATUSES, true)) {
            return back()->with('error', __('Die Akte ist bereits entschieden.'));
        }
        $application->update(['status' => $data['status']]);

        return back()->with('status', __('Status aktualisiert.'));
    }

    // ── Gespräche + Bewertungen (MVP-191) ────────────────────────────────

    public function addInterview(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('update', $application);
        $request->merge(['interviewer_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('interviewer_id'))]);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'mode' => ['required', 'in:onsite,remote,phone'],
            'interviewer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $application->interviews()->create([
            'organization_id' => $application->organization_id,
            'scheduled_at' => $data['scheduled_at'],
            'mode' => $data['mode'],
            'interviewer_id' => $data['interviewer_id'] ?? null,
            'status' => 'planned',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);
        $application->update(['status' => 'interview_planned']);

        return back()->with('status', __('Gespräch geplant.'));
    }

    public function completeInterview(Request $request, JobApplication $application, \App\Models\Applications\JobApplicationInterview $interview): RedirectResponse {
        Gate::authorize('update', $application);
        abort_unless($interview->job_application_id === $application->id, 404);
        $data = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $interview->update([
            'status' => 'done',
            'rating' => $data['rating'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: $interview->notes,
        ]);
        $application->update(['status' => 'interviewed']);

        return back()->with('status', __('Gespräch dokumentiert.'));
    }

    public function addReview(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('update', $application);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->reviews()->create([
            'organization_id' => $application->organization_id,
            'reviewer_id' => (int) Auth::id(),
            'rating' => (int) $data['rating'],
            'comment' => trim((string) ($data['comment'] ?? '')) ?: null,
        ]);

        return back()->with('status', __('Bewertung gespeichert.'));
    }

    // ── Unterlagen (MVP-190) ─────────────────────────────────────────────

    public function addDocument(Request $request, JobApplication $application, DocumentService $documents): RedirectResponse {
        Gate::authorize('update', $application);
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'label' => ['nullable', 'string', 'max:200'],
        ]);

        $document = $documents->create(null, $this->actor(), [
            'title' => (string) ($request->input('label') ?: __('Bewerberunterlage')),
            'document_type' => DocumentType::Other->value,
        ], $request->file('file'));

        $application->documents()->create([
            'organization_id' => $application->organization_id,
            'document_id' => $document->id,
            'label' => $request->input('label'),
        ]);
        $application->audit('recruiting.document_attached', ['document_id' => $document->id]);

        return back()->with('status', __('Unterlage abgelegt.'));
    }

    // ── Entscheidung + Datenschutz (MVP-191/192) ─────────────────────────

    public function decide(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('decide', $application);
        $data = $request->validate([
            'decision' => ['required', 'in:offer,accepted,rejected,withdrawn,talent_pool'],
            'note' => ['nullable', 'string', 'max:1000'],
            'talent_pool_consent' => ['nullable', 'boolean'],
        ]);

        try {
            $this->recruiting->decide($application, $data['decision'], $data['note'] ?? null, $this->actor(), (bool) ($data['talent_pool_consent'] ?? false));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Entscheidung dokumentiert.'));
    }

    /** Auskunft/Export (Art. 15 DSGVO): strukturierte JSON-Kopie. */
    public function export(JobApplication $application): SymfonyResponse {
        Gate::authorize('privacy', $application);
        $application->load(['requisition', 'interviews', 'documents']);

        $payload = $this->recruiting->export($application);
        $application->audit('recruiting.application_exported', ['by' => (int) Auth::id()]);

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="bewerbung-auskunft-' . $application->getKey() . '.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function anonymize(JobApplication $application): RedirectResponse {
        Gate::authorize('privacy', $application);

        $this->recruiting->anonymize($application, $this->actor());

        return back()->with('status', __('Bewerberdaten anonymisiert — die Akte bleibt als anonymer Nachweis erhalten.'));
    }

    // ── Onboarding-Übergabe (MVP-193) ────────────────────────────────────

    public function createDraft(Request $request, JobApplication $application): RedirectResponse {
        Gate::authorize('decide', $application);
        $data = $request->validate([
            'qualifications' => ['nullable', 'string', 'max:2000'],
        ]);

        $qualifications = array_values(array_filter(array_map('trim', explode("\n", (string) ($data['qualifications'] ?? '')))));

        try {
            $this->recruiting->createEmployeeDraft($application, $this->actor(), $qualifications);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Mitarbeiter-Entwurf angelegt (kein Live-Konto).'));
    }

    public function inviteDraft(JobApplication $application, EmployeeDraft $draft): RedirectResponse {
        Gate::authorize('invite', $draft);
        abort_unless($draft->job_application_id === $application->id, 404);

        try {
            $user = $this->recruiting->inviteFromDraft($draft, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Konto für :name angelegt (Passwort-Änderung beim ersten Login erzwungen).', ['name' => $user->name]));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
