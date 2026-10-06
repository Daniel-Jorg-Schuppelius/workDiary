<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRequisitionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Enums\Applications\{JobPostingStatus, JobRequisitionStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Applications\{JobPosting, JobRequisition};
use App\Models\Platform\User;
use App\Support\SortableQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

/**
 * Stellenbedarf + Veröffentlichungskanäle (Feature 068, MVP-189).
 */
class JobRequisitionController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize('viewAny', JobRequisition::class);

        $statusFilter = JobRequisitionStatus::tryFrom($request->string('status')->toString());

        $query = JobRequisition::query()
            ->withCount('applications')
            ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter));

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'title' => 'title',
            'department' => 'department',
            'status' => 'status',
            'applications' => 'applications_count',
            'target_start' => 'target_start_on',
            'created' => 'id',
        ], 'created', 'desc');

        return view('applications.recruiting.requisitions.index', [
            'requisitions' => $query->paginate(25)->withQueryString(),
            'statuses' => JobRequisitionStatus::cases(),
            'filters' => ['status' => $statusFilter->value ?? ''],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', JobRequisition::class);

        return view('applications.recruiting.requisitions._form_dialog', [
            'requisition' => new JobRequisition(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', JobRequisition::class);

        /** @var User $actor */
        $actor = Auth::user();
        $requisition = JobRequisition::query()->create([
            ...$this->validated($request),
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $actor->id,
        ]);
        $requisition->audit('recruiting.requisition_created', ['title' => $requisition->title]);

        return redirect()->route('recruiting.requisitions.show', $requisition)->with('success', __('Stelle angelegt.'));
    }

    public function show(JobRequisition $requisition): View {
        Gate::authorize('view', $requisition);
        $requisition->load(['postings', 'applications' => fn($q) => $q->orderByDesc('id'), 'responsible']);

        return view('applications.recruiting.requisitions.show', ['requisition' => $requisition]);
    }

    public function edit(JobRequisition $requisition): View {
        Gate::authorize('update', $requisition);

        return view('applications.recruiting.requisitions._form_dialog', [
            'requisition' => $requisition,
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $requisition->update($this->validated($request));

        return redirect()->route('recruiting.requisitions.show', $requisition)->with('success', __('Stelle aktualisiert.'));
    }

    public function updateStatus(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $data = $request->validate(['status' => ['required', Rule::enum(JobRequisitionStatus::class)]]);
        $requisition->update(['status' => JobRequisitionStatus::from($data['status'])]);

        return back()->with('success', __('Status aktualisiert.'));
    }

    public function addPosting(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $data = $request->validate([
            'channel' => ['required', 'in:' . implode(',', JobPosting::CHANNELS)],
            'reference' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $requisition->postings()->create([
            'organization_id' => $requisition->organization_id,
            'channel' => $data['channel'],
            'reference' => $data['reference'] ?? null,
            'url' => $data['url'] ?? null,
            'published_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => JobPostingStatus::Published,
        ]);

        return back()->with('success', __('Veröffentlichung dokumentiert.'));
    }

    public function closePosting(JobRequisition $requisition, JobPosting $posting): RedirectResponse {
        Gate::authorize('update', $requisition);
        abort_unless($posting->job_requisition_id === $requisition->id, 404);
        if ($posting->status->canTransitionTo(JobPostingStatus::Closed)) {
            $posting->update(['status' => JobPostingStatus::Closed]);
        }

        return back()->with('success', __('Veröffentlichung geschlossen.'));
    }

    /** Dialog zur Karriereseite: öffentliche Inhalte und Fristen, vorbelegt aus der bestehenden Veröffentlichung. */
    public function editCareer(JobRequisition $requisition): View {
        Gate::authorize('update', $requisition);

        return view('applications.recruiting.requisitions._career_dialog', [
            'requisition' => $requisition,
            'posting' => $requisition->postings()->where('channel', 'website')->first() ?? new JobPosting(),
        ]);
    }

    /**
     * MVP-437: veröffentlicht die Stelle im öffentlichen Karrierebereich —
     * explizite Aktion (nie automatisch), getrennte öffentliche Inhaltsfelder,
     * stabiler Slug. Nutzt genau eine `website`-Veröffentlichung je Stelle als
     * Karriereseite.
     */
    public function publishCareer(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $data = $request->validate([
            'public_title' => ['required', 'string', 'max:200'],
            'public_summary' => ['nullable', 'string', 'max:500'],
            'public_description' => ['nullable', 'string', 'max:10000'],
            'public_tasks' => ['nullable', 'string', 'max:10000'],
            'public_requirements' => ['nullable', 'string', 'max:10000'],
            'public_benefits' => ['nullable', 'string', 'max:10000'],
            'work_location' => ['nullable', 'string', 'max:200'],
            // Ein vergangenes Datum liefe beim nächsten Tageslauf sofort wieder ab.
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        /** @var JobPosting $posting */
        $posting = $requisition->postings()->firstOrNew(['channel' => 'website']);
        $posting->organization_id = $requisition->organization_id;
        $posting->fill([
            'public_title' => $data['public_title'],
            'public_summary' => $data['public_summary'] ?? null,
            'public_description' => $data['public_description'] ?? null,
            'public_tasks' => $data['public_tasks'] ?? null,
            'public_requirements' => $data['public_requirements'] ?? null,
            'public_benefits' => $data['public_benefits'] ?? null,
            'work_location' => $data['work_location'] ?? null,
            'application_deadline' => $data['application_deadline'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        $posting->public_slug = $posting->ensurePublicSlug($data['public_title']);
        $posting->status = JobPostingStatus::Published;
        $posting->published_at ??= now();
        $posting->save();
        $posting->audit('recruiting.posting_published', ['slug' => $posting->public_slug]);

        return back()->with('success', __('Stelle im Karrierebereich veröffentlicht.'));
    }

    /**
     * MVP-437: pausiert die öffentliche Karriereseite — sichtbar (Vorschau),
     * aber nicht bewerbbar.
     */
    public function pauseCareer(JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $posting = $requisition->postings()->where('channel', 'website')->first();
        if ($posting instanceof JobPosting && $posting->status->canTransitionTo(JobPostingStatus::Paused)) {
            $posting->update(['status' => JobPostingStatus::Paused]);
            $posting->audit('recruiting.posting_paused', []);
        }

        return back()->with('success', __('Karriere-Veröffentlichung pausiert.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id')),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:120'],
            'profile' => ['nullable', 'string', 'max:10000'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:999'],
            'employment_type' => ['required', 'in:' . implode(',', JobRequisition::EMPLOYMENT_TYPES)],
            'budget_note' => ['nullable', 'string', 'max:500'],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'target_start_on' => ['nullable', 'date'],
        ]);
    }
}
