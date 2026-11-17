<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delegation\StoreWorkDelegationRequest;
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Services\Delegation\WorkDelegationService;
use App\Support\{MorphMap, Sqid};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Vertretung bei Abwesenheit (Feature 202, MVP-1647): Liste, Anlage-Dialog
 * (frei, aus einem genehmigten Urlaub oder — durch die Leitung — aus einer
 * Krankmeldung) und „Vertretung beenden“. Ohne `user.delegation.manage` sieht
 * und regelt jede Person nur die Vertretungen, an denen sie beteiligt ist.
 */
class WorkDelegationController extends Controller {
    use ResolvesCurrentOrganization;

    private const int PER_PAGE = 25;

    public function __construct(private readonly WorkDelegationService $delegations) {}

    public function index(Request $request): View {
        $viewer = $this->authUser();
        $organization = $this->currentOrganization();
        $canManage = Gate::allows('manage', WorkDelegation::class);
        $all = $canManage && $request->boolean('all');
        $past = $request->string('state')->toString() === 'past';
        $today = $this->delegations->today();

        $query = WorkDelegation::query()
            ->where('organization_id', $organization->id)
            ->with(['represented', 'deputy:id,name', 'creator:id,name', 'ender:id,name']);
        if (! $all) {
            $query->where(static fn (Builder $q) => $q->where('represented_user_id', $viewer->id)->orWhere('deputy_user_id', $viewer->id));
        }
        if ($past) {
            $query->where(static fn (Builder $q) => $q->whereNotNull('ended_at')->orWhere('valid_until', '<', DateRange::day($today)))
                ->orderByDesc('valid_from');
        } else {
            $query->currentOrUpcoming($today)->orderBy('valid_from');
        }
        $delegations = $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        // Was die Vertretung übernimmt — nur für heute wirksame Zeilen der Seite.
        $work = [];
        foreach ($delegations as $delegation) {
            if ($delegation->stateOn($today) === 'active' && $delegation->represented instanceof User) {
                $work[$delegation->id] = $this->delegations->delegableWork($delegation->represented);
            }
        }

        return view('delegations.index', [
            'delegations' => $delegations,
            'work' => $work,
            'today' => $today,
            'canManage' => $canManage,
            'filters' => ['all' => $all, 'state' => $past ? 'past' : 'current'],
        ]);
    }

    public function create(Request $request): View {
        $viewer = $this->authUser();
        $organization = $this->currentOrganization();
        $today = $this->delegations->today();
        $absence = $this->absenceFromQuery($request);

        if ($absence !== null) {
            $suggestion = $this->delegations->suggestionFor($absence);
            abort_if($suggestion === null, 404);
            Gate::authorize('createFor', [WorkDelegation::class, $suggestion['represented']]);
            $prefill = $suggestion + ['absence' => $absence->getMorphClass() . ':' . $absence->sqid];
        } else {
            $represented = $viewer;
            $userKey = $request->string('user')->toString();
            if ($userKey !== '') {
                $represented = User::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(User::class, $userKey))->firstOrFail();
            }
            Gate::authorize('createFor', [WorkDelegation::class, $represented]);
            $prefill = [
                'represented' => $represented,
                'deputy_user_id' => $represented->deputy_user_id !== null ? (int) $represented->deputy_user_id : null,
                'valid_from' => $today,
                'valid_until' => null,
                'kind' => WorkDelegationKind::Other,
                'absence' => null,
            ];
        }

        $members = User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']);

        return view('delegations._form_dialog', [
            'prefill' => $prefill,
            'members' => $members,
            // Für andere anlegen nur mit Leitungsrecht; aus einer Abwesenheit steht die Person fest.
            'canChooseRepresented' => $absence === null && Gate::allows('manage', WorkDelegation::class),
            'work' => $this->delegations->delegableWork($prefill['represented']),
        ]);
    }

    public function store(StoreWorkDelegationRequest $request): RedirectResponse {
        $actor = $this->authUser();
        $organization = $this->currentOrganization();
        $data = $request->validated();

        $represented = User::query()->where('organization_id', $organization->id)->findOrFail((int) $data['represented_user_id']);
        $deputy = User::query()->where('organization_id', $organization->id)->findOrFail((int) $data['deputy_user_id']);
        Gate::authorize('createFor', [WorkDelegation::class, $represented]);

        $absence = $this->absenceFromReference((string) ($data['absence'] ?? ''));

        $this->delegations->create(
            $represented,
            $deputy,
            CarbonImmutable::parse((string) $data['valid_from']),
            ($data['valid_until'] ?? null) !== null ? CarbonImmutable::parse((string) $data['valid_until']) : null,
            WorkDelegationKind::from((string) $data['kind']),
            $absence,
            is_string($data['note'] ?? null) ? $data['note'] : null,
            $actor,
        );

        return redirect()->toList('delegations.index')->with('success', __('delegation.flash.created', ['name' => $deputy->name]));
    }

    public function end(WorkDelegation $workDelegation): RedirectResponse {
        $this->assertSameOrganization($workDelegation);
        Gate::authorize('end', $workDelegation);

        $this->delegations->end($workDelegation, $this->authUser());

        return redirect()->toList('delegations.index')->with('success', __('delegation.flash.ended'));
    }

    /** `?vacation=<sqid>` oder `?sick_leave=<sqid>`; eine Krankmeldung nur mit Leitungs- und Krankmelderecht. */
    private function absenceFromQuery(Request $request): Vacation|SickLeave|null {
        $vacationKey = $request->string('vacation')->toString();
        if ($vacationKey !== '') {
            $vacation = Vacation::query()->whereKey(Sqid::decode(Vacation::class, $vacationKey))->firstOrFail();
            $this->assertSameOrganization($vacation);

            return $vacation;
        }
        $sickKey = $request->string('sick_leave')->toString();
        if ($sickKey !== '') {
            $this->authorizeSickLeaveSource();
            $sickLeave = SickLeave::query()->whereKey(Sqid::decode(SickLeave::class, $sickKey))->firstOrFail();
            $this->assertSameOrganization($sickLeave);

            return $sickLeave;
        }

        return null;
    }

    /** Verweis aus dem Formular: `<morph-alias>:<sqid>` eines Urlaubs oder einer Krankmeldung. */
    private function absenceFromReference(string $reference): Vacation|SickLeave|null {
        if ($reference === '' || ! str_contains($reference, ':')) {
            return null;
        }
        [$type, $key] = explode(':', $reference, 2);
        $absence = match (MorphMap::classFor($type)) {
            Vacation::class => Vacation::query()->whereKey(Sqid::decode(Vacation::class, $key))->first(),
            SickLeave::class => $this->sickLeaveForReference($key),
            default => null,
        };
        abort_if($absence === null, 422);
        $this->assertSameOrganization($absence);

        return $absence;
    }

    private function sickLeaveForReference(string $key): ?SickLeave {
        $this->authorizeSickLeaveSource();

        return SickLeave::query()->whereKey(Sqid::decode(SickLeave::class, $key))->first();
    }

    /** Krankmeldung als Anlass: Leitungsrecht der Vertretung und Einsicht in Krankmeldungen. */
    private function authorizeSickLeaveSource(): void {
        $viewer = $this->authUser();
        abort_unless(Gate::allows('manage', WorkDelegation::class) && ($viewer->isAdmin() || $viewer->can(Permission::SickLeaveViewAny->value)), 403);
    }
}
