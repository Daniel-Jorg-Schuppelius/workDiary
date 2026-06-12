<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementReviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsManagementReview, IsmsScope};
use App\Models\User;
use App\Services\Isms\AuditService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Managementbewertung (Feature 046, Inkrement C): Liste je Organisation
 * (Nr, Datum, Scope, Status, Freigeber), Modal-CRUD für Entwürfe,
 * Freigeben-Aktion (mit Bestätigung; setzt Person + Zeitpunkt) und
 * Read-Only-Anzeige freigegebener Protokolle. Die UNVERÄNDERLICHKEIT
 * freigegebener Bewertungen erzwingt der AuditService. Autorisierung
 * über IsmsManagementReviewPolicy (isms.viewAny/view/manage).
 */
class ManagementReviewController extends Controller {
    public function __construct(
        private readonly AuditService $service,
        private readonly SqidEncoder $sqids,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', IsmsManagementReview::class);

        return view('isms.reviews.index', [
            'reviews' => IsmsManagementReview::query()
                ->with(['scope', 'approvedBy'])
                ->orderByDesc('review_no')
                ->paginate(25)
                ->withQueryString(),
            'canManage' => Gate::allows('create', IsmsManagementReview::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsManagementReview::class);

        return view('isms.reviews._form_dialog', [
            'review' => null,
            'scopes' => $this->scopeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsManagementReview::class);

        $data = $this->validateReview($request, withScope: true);

        /** @var User $creator */
        $creator = Auth::user();
        $scope = $this->resolveScope($data['scope'])
            ?? IsmsScope::query()->orderByDesc('is_default')->firstOrFail();
        $this->service->createReview($creator, $scope, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.review_created'));
    }

    /** Read-Only-Anzeige (insb. freigegebene Protokolle) — Modal. */
    public function show(IsmsManagementReview $review): View {
        Gate::authorize('view', $review);

        return view('isms.reviews._show_dialog', [
            'review' => $review->load(['scope', 'approvedBy']),
        ]);
    }

    public function edit(IsmsManagementReview $review): View {
        Gate::authorize('update', $review);

        return view('isms.reviews._form_dialog', [
            'review' => $review,
            'scopes' => $this->scopeOptions(),
        ]);
    }

    public function update(Request $request, IsmsManagementReview $review): RedirectResponse {
        Gate::authorize('update', $review);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateReview($review, $actor, $this->validateReview($request, withScope: false));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.review_updated'));
    }

    /** Freigabe (draft → approved): setzt Person + Zeitpunkt; danach unveränderlich. */
    public function approve(IsmsManagementReview $review): RedirectResponse {
        Gate::authorize('approve', $review);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->approveReview($review, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.review_approved'));
    }

    public function destroy(IsmsManagementReview $review): RedirectResponse {
        Gate::authorize('delete', $review);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteReview($review, $actor);

        return redirect()
            ->route('isms.reviews.index')
            ->with('success', __('isms.flash.review_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReview(Request $request, bool $withScope): array {
        return $request->validate([
            ...($withScope ? ['scope' => ['required', 'string', 'max:64']] : []),
            'held_on' => ['required', 'date'],
            'participants' => ['required', 'string', 'max:5000'],
            'inputs' => ['required', 'string', 'max:20000'],
            'decisions' => ['required', 'string', 'max:20000'],
            'follow_ups' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    /**
     * @return Collection<int, IsmsScope>
     */
    private function scopeOptions(): Collection {
        return IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get();
    }

    /** Löst den Scope-Formularparameter (Sqid) auf (Muster ConformityController). */
    private function resolveScope(mixed $sqid): ?IsmsScope {
        if (! is_string($sqid) || $sqid === '') {
            return null;
        }

        $id = $this->sqids->decode(IsmsScope::class, $sqid);

        return $id === null ? null : IsmsScope::query()->whereKey($id)->first();
    }
}
