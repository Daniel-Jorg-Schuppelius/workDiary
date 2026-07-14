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

use App\Http\Controllers\Controller;
use App\Models\Applications\{JobPosting, JobRequisition};
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Stellenbedarf + Veröffentlichungskanäle (Feature 068, MVP-189).
 */
class JobRequisitionController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', JobRequisition::class);

        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, JobRequisition::STATUSES, true) ? $status : '';

        return view('applications.recruiting.requisitions.index', [
            'requisitions' => JobRequisition::query()
                ->withCount('applications')
                ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'statuses' => JobRequisition::STATUSES,
            'filters' => ['status' => $statusFilter],
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
            'organization_id' => (int) $actor->organization_id,
            'created_by' => $actor->id,
        ]);
        $requisition->audit('recruiting.requisition_created', ['title' => $requisition->title]);

        return redirect()->route('recruiting.requisitions.show', $requisition)->with('status', __('Stelle angelegt.'));
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

        return redirect()->route('recruiting.requisitions.show', $requisition)->with('status', __('Stelle aktualisiert.'));
    }

    public function updateStatus(Request $request, JobRequisition $requisition): RedirectResponse {
        Gate::authorize('update', $requisition);
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', JobRequisition::STATUSES)]]);
        $requisition->update(['status' => $data['status']]);

        return back()->with('status', __('Status aktualisiert.'));
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
            'status' => 'published',
        ]);

        return back()->with('status', __('Veröffentlichung dokumentiert.'));
    }

    public function closePosting(JobRequisition $requisition, JobPosting $posting): RedirectResponse {
        Gate::authorize('update', $requisition);
        abort_unless($posting->job_requisition_id === $requisition->id, 404);
        $posting->update(['status' => 'closed']);

        return back()->with('status', __('Veröffentlichung geschlossen.'));
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
