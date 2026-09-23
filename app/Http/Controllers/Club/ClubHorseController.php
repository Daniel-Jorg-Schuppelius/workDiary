<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveClubHorseRequest;
use App\Models\Club\{ClubGroup, ClubHorse, ClubHorseAssignment, ClubHorseUse, ClubMember, ClubResourceClearance, ClubResourceClosure};
use App\Services\Club\ClubHorseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Pferdeprofile (Feature 159, MVP-854): Schul-/Privatpferde, Reitgruppen, Einsatzgrenzen, Einsätze. */
class ClubHorseController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubHorseService $horses,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubHorse::class);
        $now = CarbonImmutable::now();
        $closed = ClubResourceClosure::query()->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->pluck('club_resource_id')->unique();

        return view('club.horses.index', [
            'horses' => ClubHorse::query()->with(['resource:id,teardown_minutes,requires_clearance', 'owner:id,first_name,last_name', 'groups:id,name'])->withCount('assignments')->orderBy('name')->get(),
            'closedResourceIds' => $closed,
            'canManage' => Gate::allows('create', ClubHorse::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubHorse::class);

        return view('club.horses._form_dialog', ['horse' => null] + $this->formOptions());
    }

    public function store(SaveClubHorseRequest $request): RedirectResponse {
        Gate::authorize('create', ClubHorse::class);
        $horse = $this->horses->create($this->currentOrganization(), $request->validated());

        return redirect()->route('club.horses.show', $horse)->with('success', __('club.horses.flash.saved'));
    }

    public function show(ClubHorse $horse): View {
        Gate::authorize('view', $horse);
        $now = CarbonImmutable::now();
        $resource = $horse->resource()->firstOrFail();

        return view('club.horses.show', [
            'horse' => $horse->load(['owner:id,first_name,last_name', 'groups:id,name']),
            'resource' => $resource,
            'assignments' => ClubHorseAssignment::query()->where('club_horse_id', $horse->id)->whereHas('event', fn($q) => $q->where('ended_at', '>=', $now->subDays(7)))->with(['event:id,title,started_at,ended_at,cancelled_at', 'member:id,first_name,last_name'])->get()->sortBy(fn(ClubHorseAssignment $a) => $a->event?->started_at)->values(),
            'closures' => ClubResourceClosure::query()->where('club_resource_id', $resource->id)->where('ends_at', '>=', $now->subDays(30))->orderBy('starts_at')->get(),
            'clearances' => ClubResourceClearance::query()->where('club_resource_id', $resource->id)->with('member:id,first_name,last_name')->get()->sortBy(fn(ClubResourceClearance $c): string => $c->member?->last_name . ' ' . $c->member?->first_name)->values(),
            'minutesMonth' => $this->horses->minutesFor($horse, $now->startOfMonth(), $now->addMonth()->startOfMonth()),
            'recentUses' => ClubHorseUse::query()->where('club_horse_id', $horse->id)->with(['event:id,title,started_at', 'member:id,first_name,last_name'])->orderByDesc('recorded_at')->limit(30)->get(),
            'canManage' => Gate::allows('update', $horse),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function edit(ClubHorse $horse): View {
        Gate::authorize('update', $horse);

        return view('club.horses._form_dialog', ['horse' => $horse->load(['groups', 'resource'])] + $this->formOptions());
    }

    public function update(SaveClubHorseRequest $request, ClubHorse $horse): RedirectResponse {
        Gate::authorize('update', $horse);
        $this->horses->update($horse, $request->validated());

        return redirect()->route('club.horses.show', $horse)->with('success', __('club.horses.flash.saved'));
    }

    public function destroy(ClubHorse $horse): RedirectResponse {
        Gate::authorize('delete', $horse);
        $this->horses->delete($horse);

        return redirect()->route('club.horses.index')->with('success', __('club.horses.flash.deleted'));
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no']),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
