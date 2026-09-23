<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveClubResourceRequest, SaveResourceClearanceRequest, SaveResourceClosureRequest};
use App\Models\{Asset, Room};
use App\Models\Club\{ClubMember, ClubResource, ClubResourceBooking, ClubResourceClearance, ClubResourceClosure};
use App\Models\Platform\User;
use App\Services\Club\ClubResourceService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Sportstätten und Ressourcen (Feature 159, MVP-853): Baum, Belegungen, Sperrzeiten, Freigaben. */
class ClubResourceController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubResourceService $resources,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubResource::class);
        $all = ClubResource::query()->with(['room:id,name', 'asset:id,name'])->withCount('bookings')->orderBy('sort_order')->orderBy('name')->get();
        $now = CarbonImmutable::now();
        $closedIds = ClubResourceClosure::query()->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->pluck('club_resource_id')->unique();

        return view('club.resources.index', [
            'tree' => $this->tree($all, null, 0),
            'closedIds' => $closedIds,
            'canManage' => Gate::allows('create', ClubResource::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubResource::class);

        return view('club.resources._form_dialog', ['resource' => null] + $this->formOptions(null));
    }

    public function store(SaveClubResourceRequest $request): RedirectResponse {
        Gate::authorize('create', ClubResource::class);
        $resource = $this->resources->create($this->currentOrganization(), $request->validated());

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.saved'));
    }

    public function show(ClubResource $resource): View {
        Gate::authorize('view', $resource);
        $now = CarbonImmutable::now();

        return view('club.resources.show', [
            'resource' => $resource->load(['parent', 'children', 'room:id,name', 'asset:id,name,asset_no']),
            'bookings' => ClubResourceBooking::query()->where('club_resource_id', $resource->id)->where('ends_at', '>=', $now->subDay())->with(['event:id,title,started_at,ended_at,cancelled_at', 'member:id,first_name,last_name'])->orderBy('starts_at')->limit(100)->get(),
            'closures' => ClubResourceClosure::query()->where('club_resource_id', $resource->id)->where('ends_at', '>=', $now->subDays(30))->with('createdBy:id,name')->orderBy('starts_at')->get(),
            'clearances' => ClubResourceClearance::query()->where('club_resource_id', $resource->id)->with(['member:id,first_name,last_name', 'grantedBy:id,name'])->get()->sortBy(fn(ClubResourceClearance $c): string => $c->member?->last_name . ' ' . $c->member?->first_name)->values(),
            'assetBlocked' => $resource->asset_id !== null && $resource->asset !== null && ! app(\App\Services\Asset\AssetUsageGuard::class)->isUsable($resource->asset, ClubResourceService::ASSET_CONTEXT),
            'canManage' => Gate::allows('update', $resource),
            'canClear' => Gate::allows('clear', $resource),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function edit(ClubResource $resource): View {
        Gate::authorize('update', $resource);

        return view('club.resources._form_dialog', ['resource' => $resource] + $this->formOptions($resource));
    }

    public function update(SaveClubResourceRequest $request, ClubResource $resource): RedirectResponse {
        Gate::authorize('update', $resource);
        $this->resources->update($resource, $request->validated());

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.saved'));
    }

    public function destroy(ClubResource $resource): RedirectResponse {
        Gate::authorize('delete', $resource);
        $this->resources->delete($resource);

        return redirect()->route('club.resources.index')->with('success', __('club.resources.flash.deleted'));
    }

    public function closureDialog(ClubResource $resource): View {
        Gate::authorize('close', $resource);

        return view('club.resources._closure_dialog', ['resource' => $resource, 'formTz' => Tz::current()]);
    }

    public function close(SaveResourceClosureRequest $request, ClubResource $resource): RedirectResponse {
        Gate::authorize('close', $resource);
        $data = $request->validated();
        $tz = Tz::isValid($data['timezone'] ?? null) && ($data['timezone'] ?? 'UTC') !== 'UTC' ? (string) $data['timezone'] : Tz::current();
        /** @var User $actor */
        $actor = Auth::user();
        $closure = $this->resources->close($resource, CarbonImmutable::parse((string) $data['starts_at'], $tz)->utc(), CarbonImmutable::parse((string) $data['ends_at'], $tz)->utc(), (string) $data['reason'], $actor);

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.closed', ['count' => ClubResourceBooking::query()->whereNotNull('flagged_at')->where('club_resource_id', $resource->id)->count(), 'reason' => $closure->reason]));
    }

    public function reopen(ClubResource $resource, ClubResourceClosure $closure): RedirectResponse {
        Gate::authorize('close', $resource);
        abort_unless($closure->club_resource_id === $resource->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resources->reopen($closure, $actor);

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.reopened'));
    }

    public function clearanceDialog(ClubResource $resource): View {
        Gate::authorize('clear', $resource);

        return view('club.resources._clearance_dialog', [
            'resource' => $resource,
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no']),
        ]);
    }

    public function grantClearance(SaveResourceClearanceRequest $request, ClubResource $resource): RedirectResponse {
        Gate::authorize('clear', $resource);
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->whereKey((int) $data['club_member_id'])->firstOrFail();
        /** @var User $actor */
        $actor = Auth::user();
        $this->resources->grantClearance($resource, $member, $actor, isset($data['valid_to']) ? CarbonImmutable::parse((string) $data['valid_to']) : null, $data['note'] ?? null);

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.clearance_granted'));
    }

    public function revokeClearance(ClubResource $resource, ClubResourceClearance $clearance): RedirectResponse {
        Gate::authorize('clear', $resource);
        abort_unless($clearance->club_resource_id === $resource->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->resources->revokeClearance($clearance, $actor);

        return redirect()->route('club.resources.show', $resource)->with('success', __('club.resources.flash.clearance_revoked'));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ClubResource>  $all
     * @return list<array{resource: ClubResource, depth: int}>
     */
    private function tree(\Illuminate\Database\Eloquent\Collection $all, ?int $parentId, int $depth): array {
        $rows = [];
        foreach ($all->where('parent_id', $parentId) as $resource) {
            $rows[] = ['resource' => $resource, 'depth' => $depth];
            if ($depth < ClubResource::MAX_DEPTH) {
                $rows = array_merge($rows, $this->tree($all, $resource->id, $depth + 1));
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function formOptions(?ClubResource $current): array {
        return [
            'parents' => ClubResource::query()->when($current !== null, fn($q) => $q->whereKeyNot($current?->id))->orderBy('name')->get(['id', 'name', 'parent_id']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'assets' => Asset::query()->orderBy('name')->limit(500)->get(['id', 'name', 'asset_no']),
            'kindHints' => \App\Models\Club\ClubSportProfile::query()->where('is_active', true)->get(['resource_types'])->flatMap(fn(\App\Models\Club\ClubSportProfile $p) => $p->resource_types ?? [])->unique()->values(),
        ];
    }
}
