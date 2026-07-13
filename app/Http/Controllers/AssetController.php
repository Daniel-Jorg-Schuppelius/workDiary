<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Asset\{AssetOwnership, AssetStatus};
use App\Exceptions\AssetValidationException;
use App\Http\Requests\SaveAssetRequest;
use App\Models\{Asset, Tag, User};
use App\Services\Asset\{AssetDetailAssembler, AssetFormOptions, AssetService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Detail-Aggregation und Options-/Formulardaten liegen in
 * {@see AssetDetailAssembler} bzw. {@see AssetFormOptions}
 * (Refactoring Welle 2, B6b).
 */
class AssetController extends Controller {
    private const ALLOWED_SORTS = ['asset_no', 'asset_class', 'name', 'serial_no', 'location_text', 'status'];

    public function __construct(private readonly AssetFormOptions $options) {}

    /**
     * Trennt die Tag-Eingaben aus dem validierten Payload heraus (sie sind
     * keine Asset-Spalten und dürfen nicht an den AssetService durchgereicht
     * werden) und normalisiert sie für {@see HasTags::syncTagsFromInput()}.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: list<int>, 1: list<string>}
     */
    private function extractTagInput(array &$payload): array {
        // tag_ids kommen als opake Sqids aus dem Tag-Picker; rohe numerische
        // IDs werden ebenfalls toleriert (Sqid::decodeOrNumeric).
        $tagIds = array_values(array_filter(array_map(
            static fn($v) => is_scalar($v) ? Sqid::decodeOrNumeric(Tag::class, (string) $v) : null,
            (array) ($payload['tag_ids'] ?? []),
        ), static fn($v): bool => $v !== null));
        $newTags = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($payload['new_tags'] ?? '')),
        )));

        unset($payload['tag_ids'], $payload['new_tags']);

        return [$tagIds, $newTags];
    }

    public function index(Request $request): View {
        Gate::authorize('viewAny', Asset::class);

        $query = trim($request->string('q')->toString());
        $classFilter = $this->normalizeAssetClass($request->string('class')->toString());
        $statusFilter = $this->normalizeAssetStatus($request->string('status')->toString());
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $assetsQuery = Asset::query()
            ->with(['customer:id,name', 'tags:id,name,color,slug'])
            ->withCount([
                'assignments as open_assignments_count' => fn($q) => $q->whereNull('returned_at'),
                'defects as blocking_defects_count' => fn($q) => $q->blocking(),
            ])
            ->orderByRaw("case when status = ? then 1 else 0 end asc", [AssetStatus::Blocked->value])
            ->orderBy($sort, $dir);

        if ($query !== '') {
            $assetsQuery->where(function ($builder) use ($query): void {
                $builder
                    ->whereLikeEscaped('asset_no', $query)
                    ->orWhereLikeEscaped('name', $query)
                    ->orWhereLikeEscaped('serial_no', $query)
                    ->orWhereLikeEscaped('location_text', $query);
            });
        }

        if ($classFilter !== null) {
            $assetsQuery->where('asset_class', $classFilter);
        }

        if ($statusFilter !== null) {
            $assetsQuery->where('status', $statusFilter);
        }

        $assets = $assetsQuery->paginate(20)->withQueryString();
        $classOptions = $this->options->classOptions();
        $statusOptions = $this->options->statusOptions();

        $statusCounts = Asset::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $kpis = [
            'total' => (int) $statusCounts->sum(),
            'blocked' => (int) ($statusCounts[AssetStatus::Blocked->value] ?? 0),
            'maintenance' => (int) ($statusCounts[AssetStatus::InMaintenance->value] ?? 0)
                + (int) ($statusCounts[AssetStatus::InRepair->value] ?? 0),
        ];

        return view('assets.index', [
            'assets' => $assets,
            'classOptions' => $classOptions,
            'statusOptions' => $statusOptions,
            'kpis' => $kpis,
            'canCreate' => Gate::allows('create', Asset::class),
            'activeFilters' => [
                'q' => $query,
                'class' => $classFilter ?? 'all',
                'status' => $statusFilter ?? 'all',
            ],
            'sort' => $sort,
            'dir' => $dir,
            'activeFilterChips' => array_values(array_filter([
                $query !== '' ? __('Suche: :value', ['value' => $query]) : null,
                $classFilter !== null ? __('Typ: :value', ['value' => $classOptions[$classFilter] ?? $classFilter]) : null,
                $statusFilter !== null ? __('Status: :value', ['value' => $statusOptions[$statusFilter] ?? $statusFilter]) : null,
            ])),
            'hasActiveFilters' => $query !== '' || $classFilter !== null || $statusFilter !== null,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Asset::class);

        $prefill = $this->options->resolvePrefill($request);

        return view('assets._form_dialog', [
            'asset' => new Asset([
                'status' => AssetStatus::Active->value,
                'customer_id' => $prefill['customer_id'],
                'room_id' => $prefill['room_id'],
            ]),
            'classOptions' => $this->options->classOptions(),
            'statusOptions' => $this->options->statusOptionsForCreate(),
            'customers' => $this->options->customerOptions(),
            'foreignCustomers' => $this->options->foreignCustomerOptions(),
            'categoryOptions' => $this->options->categoryOptions(),
            'prefill' => $prefill,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ] + $this->options->facilityData());
    }

    public function store(SaveAssetRequest $request, AssetService $assetService): RedirectResponse {
        Gate::authorize('create', Asset::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
        [$tagIds, $newTags] = $this->extractTagInput($payload);
        $payload['owned_by'] = ($payload['customer_id'] ?? null) === null
            ? AssetOwnership::Organization->value
            : AssetOwnership::Customer->value;

        try {
            $asset = $assetService->create($user, $payload);
        } catch (AssetValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors(['status' => __($exception->getMessage())]);
        }

        $asset->syncTagsFromInput($tagIds, $newTags);

        return redirect()->route('assets.index')->with('success', __('Asset angelegt.'));
    }

    public function edit(Asset $asset): View {
        Gate::authorize('update', $asset);

        $room = $asset->room_id !== null
            ? \App\Models\Room::query()->with('floorRelation.building.site')->find($asset->room_id)
            : null;
        $prefill = [
            'customer_id' => $asset->customer_id,
            'foreign_customer_id' => $asset->foreign_customer_id,
            'site_id' => $room?->floorRelation?->building?->site_id,
            'building_id' => $room?->floorRelation?->building_id,
            'floor_id' => $room?->floor_id,
            'room_id' => $asset->room_id,
        ];

        return view('assets._form_dialog', [
            'asset' => $asset->load('tags'),
            'classOptions' => $this->options->classOptions(),
            'statusOptions' => $this->options->statusOptions(),
            'customers' => $this->options->customerOptions(),
            'foreignCustomers' => $this->options->foreignCustomerOptions(),
            'categoryOptions' => $this->options->categoryOptions(),
            'prefill' => $prefill,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ] + $this->options->facilityData());
    }

    public function update(SaveAssetRequest $request, Asset $asset, AssetService $assetService): RedirectResponse {
        Gate::authorize('update', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
        [$tagIds, $newTags] = $this->extractTagInput($payload);
        $payload['owned_by'] = ($payload['customer_id'] ?? null) === null
            ? AssetOwnership::Organization->value
            : AssetOwnership::Customer->value;

        try {
            $assetService->update($asset, $user, $payload);
        } catch (AssetValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors(['status' => __($exception->getMessage())]);
        }

        $asset->syncTagsFromInput($tagIds, $newTags);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Asset aktualisiert.'));
    }

    public function show(Asset $asset, Request $request, AssetDetailAssembler $assembler): View {
        Gate::authorize('view', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return view('assets.show', $assembler->assemble($asset, $user));
    }

    public function unblock(Asset $asset, Request $request, AssetService $assetService): RedirectResponse {
        Gate::authorize('update', $asset);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($asset->status === AssetStatus::Blocked) {
            $assetService->update($asset, $user, ['status' => AssetStatus::Active->value]);
        }

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Asset-Sperre aufgehoben.'));
    }

    private function normalizeAssetClass(string $value): ?string {
        return array_key_exists($value, $this->options->classOptions()) ? $value : null;
    }

    private function normalizeAssetStatus(string $value): ?string {
        return array_key_exists($value, $this->options->statusOptions()) ? $value : null;
    }
}
