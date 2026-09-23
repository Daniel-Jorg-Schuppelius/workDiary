<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceRequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveAttendanceRequirementRequest;
use App\Models\Club\{ClubAttendanceRequirement, ClubDepartment, ClubGroup};
use App\Services\Club\ClubCompetitionService;
use App\Support\{CsvExport, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Nachweisliste (MVP-855): vom Verein konfigurierte Anwesenheitsanforderungen, Bericht und CSV-Export. */
class ClubAttendanceRequirementController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubCompetitionService $competitions,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubAttendanceRequirement::class);
        $requirements = ClubAttendanceRequirement::query()->with(['group:id,name', 'department:id,name'])->orderBy('name')->get();
        $selectedId = Sqid::decodeOrNumeric(ClubAttendanceRequirement::class, (string) $request->query('requirement', ''));
        $selected = $selectedId !== null ? $requirements->firstWhere('id', $selectedId) : $requirements->first();
        $asOf = $this->asOf($request);

        return view('club.requirements.index', [
            'requirements' => $requirements,
            'selected' => $selected,
            'asOf' => $asOf,
            'report' => $selected !== null ? $this->competitions->complianceReport($selected, $asOf) : collect(),
            'canManage' => Gate::allows('create', ClubAttendanceRequirement::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubAttendanceRequirement::class);

        return view('club.requirements._form_dialog', ['requirement' => null] + $this->formOptions());
    }

    public function store(SaveAttendanceRequirementRequest $request): RedirectResponse {
        Gate::authorize('create', ClubAttendanceRequirement::class);
        $requirement = $this->competitions->createRequirement($this->currentOrganization(), $request->validated());

        return redirect()->toList('club.requirements.index', ['requirement' => $requirement->sqid])->with('success', __('club.competitions.flash.requirement_saved'));
    }

    public function edit(ClubAttendanceRequirement $requirement): View {
        Gate::authorize('update', $requirement);

        return view('club.requirements._form_dialog', ['requirement' => $requirement] + $this->formOptions());
    }

    public function update(SaveAttendanceRequirementRequest $request, ClubAttendanceRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $requirement);
        $this->competitions->updateRequirement($requirement, $request->validated());

        return redirect()->toList('club.requirements.index', ['requirement' => $requirement->sqid])->with('success', __('club.competitions.flash.requirement_saved'));
    }

    public function destroy(ClubAttendanceRequirement $requirement): RedirectResponse {
        Gate::authorize('delete', $requirement);
        $this->competitions->deleteRequirement($requirement);

        return redirect()->toList('club.requirements.index')->with('success', __('club.competitions.flash.requirement_deleted'));
    }

    /** Export der Nachweisliste als CSV — Zeilen: Mitglied, Nummer, bestätigte Anwesenheiten, Soll, erfüllt, letzter Termin. */
    public function export(Request $request, ClubAttendanceRequirement $requirement): StreamedResponse {
        Gate::authorize('view', $requirement);
        $asOf = $this->asOf($request);
        $rows = [];
        foreach ($this->competitions->complianceReport($requirement, $asOf) as $row) {
            $rows[] = [$row['member']->fullName(), (string) $row['member']->member_no, (string) $row['count'], (string) $row['required'], $row['met'] ? (string) __('club.label.yes') : (string) __('club.label.no'), $row['last_on']?->format('d.m.Y') ?? ''];
        }
        $requirement->audit('club.competition.requirementExported', ['as_of' => $asOf->toDateString(), 'rows' => count($rows)]);

        return CsvExport::streamFromRows('nachweisliste-' . $requirement->sqid . '-' . $asOf->format('Y-m-d') . '.csv', [
            (string) __('club.field.member'), (string) __('club.field.member_no'), (string) __('club.competitions.field.count'), (string) __('club.competitions.field.required'), (string) __('club.competitions.field.met'), (string) __('club.competitions.field.last_on'),
        ], $rows);
    }

    private function asOf(Request $request): CarbonImmutable {
        $raw = trim((string) $request->query('as_of', ''));
        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return CarbonImmutable::parse($raw)->startOfDay();
        }

        return CarbonImmutable::today();
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
