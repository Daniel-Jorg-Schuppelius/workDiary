<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubCountingBasis, ClubEventKind};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveGradeRequest, SaveGradeRequirementRequest, SaveGradingSystemRequest, SaveGradingVersionRequest};
use App\Models\Club\{ClubGrade, ClubGradeRequirement, ClubGradingSystem, ClubGradingVersion, ClubGroup};
use App\Models\Platform\User;
use App\Services\Club\ClubGradingService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Graduierungsordnungen (Feature 159, MVP-846): Ordnungen, Grade, Regelversionen
 * und Voraussetzungen je Zielgrad. Fachlogik im ClubGradingService.
 */
class ClubGradingController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubGradingService $grading,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubGradingSystem::class);

        return view('club.grading.index', [
            'systems' => ClubGradingSystem::query()->withCount(['grades', 'versions', 'memberGrades'])->orderBy('name')->get(),
            'enabled' => $this->grading->isEnabled($this->currentOrganization()),
            'canManage' => Gate::allows('create', ClubGradingSystem::class),
            'canSettings' => Gate::allows('create', \App\Models\Club\ClubMember::class),
        ]);
    }

    public function show(Request $request, ClubGradingSystem $system): View {
        Gate::authorize('view', $system);
        $system->load(['grades' => fn($q) => $q->withCount('memberGrades'), 'versions']);
        $versionId = Sqid::decodeOrNumeric(ClubGradingVersion::class, (string) $request->query('version', ''));
        $version = $versionId !== null ? $system->versions->firstWhere('id', $versionId) : null;
        $version ??= $system->activeVersion() ?? $system->versions->first();
        $requirements = $version !== null ? $version->requirements()->with(['previousGrade'])->get()->keyBy('club_grade_id') : collect();
        $groupNames = ClubGroup::query()->pluck('name', 'id');

        return view('club.grading.show', [
            'system' => $system,
            'version' => $version,
            'requirements' => $requirements,
            'groupNames' => $groupNames,
            'canManage' => Gate::allows('update', $system),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubGradingSystem::class);

        return view('club.grading._system_dialog', ['system' => null]);
    }

    public function store(SaveGradingSystemRequest $request): RedirectResponse {
        Gate::authorize('create', ClubGradingSystem::class);
        $system = $this->grading->createSystem($this->currentOrganization(), $request->validated());

        return redirect()->route('club.grading.show', $system)->with('success', __('club.grading.flash.system_saved'));
    }

    public function edit(ClubGradingSystem $system): View {
        Gate::authorize('update', $system);

        return view('club.grading._system_dialog', ['system' => $system]);
    }

    public function update(SaveGradingSystemRequest $request, ClubGradingSystem $system): RedirectResponse {
        Gate::authorize('update', $system);
        $this->grading->updateSystem($system, $request->validated());

        return redirect()->route('club.grading.show', $system)->with('success', __('club.grading.flash.system_saved'));
    }

    public function destroy(ClubGradingSystem $system): RedirectResponse {
        Gate::authorize('delete', $system);
        $this->grading->deleteSystem($system);

        return redirect()->route('club.grading.index')->with('success', __('club.grading.flash.system_deleted'));
    }

    // ── Grade ────────────────────────────────────────────────────────────

    public function createGrade(ClubGradingSystem $system): View {
        Gate::authorize('update', $system);

        return view('club.grading._grade_dialog', ['system' => $system, 'grade' => null]);
    }

    public function storeGrade(SaveGradeRequest $request, ClubGradingSystem $system): RedirectResponse {
        Gate::authorize('update', $system);
        $this->grading->createGrade($system, $request->validated());

        return redirect()->route('club.grading.show', $system)->with('success', __('club.grading.flash.grade_saved'));
    }

    public function editGrade(ClubGrade $grade): View {
        $system = $grade->system()->firstOrFail();
        Gate::authorize('update', $system);

        return view('club.grading._grade_dialog', ['system' => $system, 'grade' => $grade]);
    }

    public function updateGrade(SaveGradeRequest $request, ClubGrade $grade): RedirectResponse {
        $system = $grade->system()->firstOrFail();
        Gate::authorize('update', $system);
        $this->grading->updateGrade($grade, $request->validated());

        return redirect()->route('club.grading.show', $system)->with('success', __('club.grading.flash.grade_saved'));
    }

    public function destroyGrade(ClubGrade $grade): RedirectResponse {
        $system = $grade->system()->firstOrFail();
        Gate::authorize('update', $system);
        $this->grading->deleteGrade($grade);

        return redirect()->route('club.grading.show', $system)->with('success', __('club.grading.flash.grade_deleted'));
    }

    // ── Regelversionen ───────────────────────────────────────────────────

    public function storeVersion(ClubGradingSystem $system): RedirectResponse {
        Gate::authorize('update', $system);
        $version = $this->grading->createVersion($system);

        return redirect()->route('club.grading.show', [$system, 'version' => $version->sqid])->with('success', __('club.grading.flash.version_created', ['no' => $version->version_no]));
    }

    public function editVersion(ClubGradingVersion $version): View {
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);

        return view('club.grading._version_dialog', ['system' => $system, 'version' => $version]);
    }

    public function updateVersion(SaveGradingVersionRequest $request, ClubGradingVersion $version): RedirectResponse {
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);
        $this->grading->updateVersion($version, $request->validated());

        return redirect()->route('club.grading.show', [$system, 'version' => $version->sqid])->with('success', __('club.grading.flash.version_saved'));
    }

    public function activateVersion(ClubGradingVersion $version): RedirectResponse {
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);
        /** @var User $actor */
        $actor = Auth::user();
        $this->grading->activateVersion($version, $actor);

        return redirect()->route('club.grading.show', [$system, 'version' => $version->sqid])->with('success', __('club.grading.flash.version_activated', ['no' => $version->version_no]));
    }

    // ── Voraussetzungen ──────────────────────────────────────────────────

    public function editRequirement(ClubGradingVersion $version, ClubGrade $grade): View {
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);
        abort_unless($grade->club_grading_system_id === $system->id, 404);
        /** @var ClubGradeRequirement|null $requirement */
        $requirement = $version->requirements()->where('club_grade_id', $grade->id)->first();

        return view('club.grading._requirement_dialog', [
            'system' => $system,
            'version' => $version,
            'grade' => $grade,
            'requirement' => $requirement,
            'previousOptions' => $system->grades()->where('rank', '<', $grade->rank)->get(),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'kinds' => ClubEventKind::cases(),
            'bases' => ClubCountingBasis::cases(),
        ]);
    }

    public function updateRequirement(SaveGradeRequirementRequest $request, ClubGradingVersion $version, ClubGrade $grade): RedirectResponse {
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);
        abort_unless($grade->club_grading_system_id === $system->id, 404);
        $data = $request->validated();
        $data['counted_group_ids'] = array_values(array_filter(array_map(
            static fn(string $sqid): ?int => Sqid::decodeOrNumeric(ClubGroup::class, $sqid),
            array_map('strval', (array) ($data['counted_group_ids'] ?? [])),
        )));
        $this->grading->saveRequirement($version, $grade, $data);

        return redirect()->route('club.grading.show', [$system, 'version' => $version->sqid])->with('success', __('club.grading.flash.requirement_saved'));
    }

    public function destroyRequirement(ClubGradeRequirement $requirement): RedirectResponse {
        $version = $requirement->version()->firstOrFail();
        $system = $version->system()->firstOrFail();
        Gate::authorize('update', $system);
        $this->grading->deleteRequirement($requirement);

        return redirect()->route('club.grading.show', [$system, 'version' => $version->sqid])->with('success', __('club.grading.flash.requirement_deleted'));
    }
}
