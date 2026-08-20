<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TechnicalMeasureController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\{MeasureCategory, ReviewResult};
use App\Http\Controllers\Controller;
use App\Models\Privacy\{ProcessingActivity, TechnicalMeasure, TechnicalMeasureVersion};
use App\Services\Privacy\TechnicalMeasureService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Zentraler TOM-Katalog (Art. 32) mit Versionierung, Zuordnung und Wirksamkeitspruefung. */
class TechnicalMeasureController extends Controller {
    public function __construct(private readonly TechnicalMeasureService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', TechnicalMeasure::class);

        return view('privacy.tom.index', [
            'measures' => TechnicalMeasure::query()->orderBy('category')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', TechnicalMeasure::class);

        return view('privacy.tom._form_dialog', ['categories' => MeasureCategory::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TechnicalMeasure::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', \Illuminate\Validation\Rule::enum(MeasureCategory::class)],
            'description' => ['nullable', 'string', 'max:10000'],
            'addressed_risks' => ['nullable', 'string', 'max:10000'],
            'evidence' => ['nullable', 'string', 'max:10000'],
        ]);

        $measure = $this->service->createDraft(
            $org,
            $data['name'],
            MeasureCategory::from($data['category']),
            $this->payload($data),
            $request->user(),
        );

        return redirect()->route('dataprotection.tom.show', $measure)
            ->with('status', __('Maßnahme angelegt (Entwurf).'));
    }

    public function show(TechnicalMeasure $measure): View {
        Gate::authorize('view', $measure);

        return view('privacy.tom.show', [
            'measure' => $measure->load(['currentVersion', 'responsible', 'reviews', 'assignments.activity']),
            'versions' => $measure->versions()->get(),
            'activities' => ProcessingActivity::query()->orderBy('name')->get(['id', 'name']),
            'results' => ReviewResult::cases(),
        ]);
    }

    public function addVersion(Request $request, TechnicalMeasure $measure): RedirectResponse {
        Gate::authorize('update', $measure);
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:10000'],
            'addressed_risks' => ['nullable', 'string', 'max:10000'],
            'evidence' => ['nullable', 'string', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->addVersion($measure, $this->payload($data), $request->user(), $data['note'] ?? null);

        return back()->with('status', __('Neue Version gespeichert.'));
    }

    public function approve(Request $request, TechnicalMeasure $measure): RedirectResponse {
        Gate::authorize('update', $measure);
        $user = $request->user();
        abort_unless($user !== null, 403);
        $data = $request->validate(['version_id' => ['required', 'string']]);
        // Sqid aus dem Formular (W3.3); die Bindung an die Massnahme bleibt.
        $version = TechnicalMeasureVersion::query()->where('measure_id', $measure->id)
            ->findOrFail(\App\Support\Sqid::decodeOrAbort(TechnicalMeasureVersion::class, (string) $data['version_id']));
        $this->service->approve($measure, $version, $user);

        return back()->with('status', __('Version freigegeben.'));
    }

    public function assignActivity(Request $request, TechnicalMeasure $measure): RedirectResponse {
        Gate::authorize('update', $measure);
        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        $request->merge(['activity_id' => Sqid::decodeOrNumeric(ProcessingActivity::class, $request->input('activity_id'))]);
        $data = $request->validate(['activity_id' => ['required', 'integer']]);
        $activity = ProcessingActivity::query()
            ->where('organization_id', $measure->organization_id)
            ->findOrFail((int) $data['activity_id']);
        $this->service->assignToActivity($measure, $activity);

        return back()->with('status', __('Maßnahme der Verarbeitungstätigkeit zugeordnet.'));
    }

    public function review(Request $request, TechnicalMeasure $measure): RedirectResponse {
        Gate::authorize('update', $measure);
        $data = $request->validate([
            'result' => ['required', 'in:effective,deviation,ineffective'],
            'deviation' => ['nullable', 'string', 'max:10000'],
            'follow_up' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->service->recordReview(
            $measure,
            ReviewResult::from($data['result']),
            $data['deviation'] ?? null,
            $data['follow_up'] ?? null,
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
            $request->user(),
        );

        return back()->with('status', __('Wirksamkeitsprüfung dokumentiert.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array {
        return [
            'description' => $data['description'] ?? null,
            'addressed_risks' => $data['addressed_risks'] ?? null,
            'evidence' => $data['evidence'] ?? null,
        ];
    }
}
