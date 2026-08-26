<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCourseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Training;

use App\Enums\Training\TrainingProviderKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Training\{TrainingCourse, TrainingCourseVersion};
use App\Models\User;
use App\Services\Training\TrainingCatalogService;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Schulungskatalog (Feature 145): Kursliste mit Modal-CRUD und die
 * Detailseite mit den Kursversionen. Lerninhalte/LMS bleiben außen vor —
 * der Katalog verwaltet nur, WAS geschult wird und wie lange es gilt.
 */
class TrainingCourseController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly TrainingCatalogService $catalog,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', TrainingCourse::class);

        $onlyMandatory = $request->query('mandatory') === '1';

        $query = TrainingCourse::query()
            ->withCount(['requirements', 'assignments'])
            ->orderBy('title');
        if ($onlyMandatory) {
            $query->where('is_mandatory', true);
        }

        return view('training.courses.index', [
            'courses' => $query->paginate(30)->withQueryString(),
            'onlyMandatory' => $onlyMandatory,
            'mandatoryCount' => TrainingCourse::query()->where('is_mandatory', true)->where('is_active', true)->count(),
            'canManage' => Gate::allows('create', TrainingCourse::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', TrainingCourse::class);

        return view('training.courses._form_dialog', ['course' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TrainingCourse::class);

        /** @var User $actor */
        $actor = Auth::user();
        $course = $this->catalog->createCourse($this->currentOrganization(), $actor, $this->validateCourse($request));

        return redirect()
            ->route('training.courses.show', $course)
            ->with('success', __('training.flash.course_created'));
    }

    public function show(TrainingCourse $course): View {
        Gate::authorize('view', $course);

        $course->load([
            'versions' => fn($q) => $q->orderByDesc('version'),
            'requirements.course',
        ]);

        return view('training.courses.show', [
            'course' => $course,
            'canManage' => Gate::allows('update', $course),
            'assignmentCount' => $course->assignments()->count(),
        ]);
    }

    public function edit(TrainingCourse $course): View {
        Gate::authorize('update', $course);

        return view('training.courses._form_dialog', ['course' => $course]);
    }

    public function update(Request $request, TrainingCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $this->catalog->updateCourse($course, $this->validateCourse($request, $course));

        return redirect()
            ->back()
            ->with('success', __('training.flash.course_updated'));
    }

    public function destroy(TrainingCourse $course): RedirectResponse {
        Gate::authorize('delete', $course);

        $this->catalog->deleteCourse($course);

        return redirect()
            ->route('training.courses.index')
            ->with('success', __('training.flash.course_deleted'));
    }

    public function createVersion(TrainingCourse $course): View {
        Gate::authorize('update', $course);

        return view('training.courses._version_dialog', ['course' => $course]);
    }

    public function storeVersion(Request $request, TrainingCourse $course): RedirectResponse {
        Gate::authorize('update', $course);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'content_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->catalog->addVersion($course, $data);

        return redirect()
            ->route('training.courses.show', $course)
            ->with('success', __('training.flash.version_created'));
    }

    public function destroyVersion(TrainingCourse $course, TrainingCourseVersion $version): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless((int) $version->training_course_id === (int) $course->id, 404);

        $this->catalog->deleteVersion($version);

        return redirect()
            ->route('training.courses.show', $course)
            ->with('success', __('training.flash.version_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourse(Request $request, ?TrainingCourse $course = null): array {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'code' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'provider_kind' => ['required', 'string', Rule::enum(TrainingProviderKind::class)],
            'provider_name' => ['nullable', 'string', 'max:180'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,10000'],
            'validity_months' => ['nullable', 'integer', 'between:1,600'],
            'is_mandatory' => ['nullable', 'boolean'],
            'legal_basis' => ['nullable', 'string', 'max:180'],
            'cost_amount' => ['nullable', 'numeric', 'between:0,9999999.99'],
            'cost_currency' => ['nullable', 'string', Rule::enum(CurrencyCode::class)],
            'lead_days' => ['required', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_mandatory'] = (bool) ($data['is_mandatory'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        // Der Code bleibt nach der Anlage stabil (Anker der Profil-Vorschläge).
        if ($course !== null) {
            unset($data['code']);
        }

        return $data;
    }
}
