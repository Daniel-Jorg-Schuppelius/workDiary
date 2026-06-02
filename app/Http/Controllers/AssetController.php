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

use App\Enums\Asset\{AssetClass, AssetOwnership, AssetStatus};
use App\Exceptions\AssetValidationException;
use App\Http\Requests\SaveAssetRequest;
use App\Models\{Asset, Attachment, Building, Customer, DiaryEntry, Floor, ForeignCustomer, MaintenancePlan, MaterialUsage, Protocol, Room, Site, User};
use App\Services\Asset\{AssetService, AssetStatusVisibilityService, AssetTimelineService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssetController extends Controller {
    private const ALLOWED_SORTS = ['asset_no', 'asset_class', 'name', 'serial_no', 'location_text', 'status'];

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
            ->with(['customer:id,name'])
            ->orderByRaw("case when status = ? then 1 else 0 end asc", [AssetStatus::Blocked->value])
            ->orderBy($sort, $dir);

        if ($query !== '') {
            $assetsQuery->where(function ($builder) use ($query): void {
                $builder
                    ->where('asset_no', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('serial_no', 'like', "%{$query}%")
                    ->orWhere('location_text', 'like', "%{$query}%");
            });
        }

        if ($classFilter !== null) {
            $assetsQuery->where('asset_class', $classFilter);
        }

        if ($statusFilter !== null) {
            $assetsQuery->where('status', $statusFilter);
        }

        $assets = $assetsQuery->paginate(20)->withQueryString();
        $classOptions = $this->assetClassOptions();
        $statusOptions = $this->assetStatusOptions();

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

        $prefill = $this->resolvePrefill($request);

        return view('assets._form_dialog', [
            'asset' => new Asset([
                'status' => AssetStatus::Active->value,
                'customer_id' => $prefill['customer_id'],
                'room_id' => $prefill['room_id'],
            ]),
            'classOptions' => $this->assetClassOptions(),
            'statusOptions' => $this->assetStatusOptionsForCreate(),
            'customers' => $this->customerOptions(),
            'foreignCustomers' => $this->foreignCustomerOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'prefill' => $prefill,
        ] + $this->facilityData());
    }

    public function store(SaveAssetRequest $request, AssetService $assetService): RedirectResponse {
        Gate::authorize('create', Asset::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
        $payload['owned_by'] = ($payload['customer_id'] ?? null) === null
            ? AssetOwnership::Organization->value
            : AssetOwnership::Customer->value;

        try {
            $assetService->create($user, $payload);
        } catch (AssetValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors(['status' => __($exception->getMessage())]);
        }

        return redirect()->route('assets.index')->with('success', __('Asset angelegt.'));
    }

    public function edit(Asset $asset): View {
        Gate::authorize('update', $asset);

        $room = $asset->room_id !== null
            ? Room::query()->with('floorRelation.building.site')->find($asset->room_id)
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
            'asset' => $asset,
            'classOptions' => $this->assetClassOptions(),
            'statusOptions' => $this->assetStatusOptions(),
            'customers' => $this->customerOptions(),
            'foreignCustomers' => $this->foreignCustomerOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'prefill' => $prefill,
        ] + $this->facilityData());
    }

    public function update(SaveAssetRequest $request, Asset $asset, AssetService $assetService): RedirectResponse {
        Gate::authorize('update', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
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

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Asset aktualisiert.'));
    }

    public function show(
        Asset $asset,
        Request $request,
        AssetTimelineService $assetTimeline,
        AssetStatusVisibilityService $assetStatusVisibility,
    ): View {
        Gate::authorize('view', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $asset->load(['customer:id,name', 'room.floorRelation.building.site', 'softwareInstallations.software', 'operatingSystem.software']);
        $asset->loadCount(['diaryEntries', 'protocols', 'materialUsages', 'attachments']);

        $diaryEntries = $asset->diaryEntries()
            ->with(['user:id,name', 'project:id,name'])
            ->limit(12)
            ->get()
            ->filter(fn(DiaryEntry $entry): bool => Gate::forUser($user)->allows('view', $entry))
            ->values();

        $protocols = $asset->protocols()
            ->with(['creator:id,name'])
            ->limit(12)
            ->get()
            ->filter(fn(Protocol $protocol): bool => Gate::forUser($user)->allows('view', $protocol))
            ->values();

        $materialUsages = $asset->materialUsages()
            ->with(['timesheet:id,work_date,user_id', 'timesheet.user:id,name'])
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->filter(fn(MaterialUsage $usage): bool => Gate::forUser($user)->allows('view', $usage))
            ->values();

        $attachments = $asset->attachments()
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->filter(fn(Attachment $attachment): bool => Gate::forUser($user)->allows('view', $attachment))
            ->values();

        $visibleDiaryIds = $diaryEntries->pluck('id')->all();
        $visibleProtocolIds = $protocols->pluck('id')->all();
        $visibleMaterialIds = $materialUsages->pluck('id')->all();
        $visibleAttachmentIds = $attachments->pluck('id')->all();

        $timelineEntries = collect($assetTimeline->build($asset, 24))
            ->filter(function (array $event) use ($visibleAttachmentIds, $visibleDiaryIds, $visibleMaterialIds, $visibleProtocolIds): bool {
                $kind = (string) ($event['kind'] ?? '');
                $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
                $id = (int) ($payload['id'] ?? 0);

                return match ($kind) {
                    'order.linked' => in_array($id, $visibleDiaryIds, true),
                    'protocol.linked' => in_array($id, $visibleProtocolIds, true),
                    'material.linked' => in_array($id, $visibleMaterialIds, true),
                    'attachment.linked' => in_array($id, $visibleAttachmentIds, true),
                    default => true,
                };
            })
            ->map(fn(array $event): array => $this->formatTimelineEvent($event))
            ->values();

        $visibilitySummary = $assetStatusVisibility->summarize($asset);

        $maintenancePlans = $asset->maintenancePlans()->get();
        $intervalKindOptions = collect(\App\Enums\Asset\MaintenanceIntervalKind::cases())
            ->mapWithKeys(fn(\App\Enums\Asset\MaintenanceIntervalKind $k): array => [$k->value => match ($k) {
                \App\Enums\Asset\MaintenanceIntervalKind::Days => __('Tage'),
                \App\Enums\Asset\MaintenanceIntervalKind::Weeks => __('Wochen'),
                \App\Enums\Asset\MaintenanceIntervalKind::Months => __('Monate'),
                \App\Enums\Asset\MaintenanceIntervalKind::OperatingHours => __('Betriebsstunden'),
                \App\Enums\Asset\MaintenanceIntervalKind::Kilometers => __('Kilometer'),
            }])
            ->all();
        $canManageMaintenance = Gate::forUser($user)->allows('create', MaintenancePlan::class);

        return view('assets.show', [
            'asset' => $asset,
            'classOptions' => $this->assetClassOptions(),
            'statusOptions' => $this->assetStatusOptions(),
            'diaryEntries' => $diaryEntries,
            'protocols' => $protocols,
            'materialUsages' => $materialUsages,
            'attachments' => $attachments,
            'timelineEntries' => $timelineEntries,
            'statusSummary' => $visibilitySummary,
            'canUnblock' => Gate::forUser($user)->allows('update', $asset),
            'maintenancePlans' => $maintenancePlans,
            'intervalKindOptions' => $intervalKindOptions,
            'canManageMaintenance' => $canManageMaintenance,
            'visibleCounts' => [
                'diary' => $diaryEntries->count(),
                'protocols' => $protocols->count(),
                'material' => $materialUsages->count(),
                'attachments' => $attachments->count(),
            ],
        ]);
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

    /**
     * @param  array<string, mixed>  $event
     * @return array{kind: string, title: string, detail: string, occurred_at_formatted: string}
     */
    private function formatTimelineEvent(array $event): array {
        $kind = (string) ($event['kind'] ?? '');
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $auditEvent = (string) ($payload['event'] ?? '');

        $title = match ($kind) {
            'order.linked' => __('Auftrag verknüpft'),
            'protocol.linked' => __('Protokoll verknüpft'),
            'material.linked' => __('Materialeinsatz verknüpft'),
            'attachment.linked' => __('Anhang verknüpft'),
            'asset.audit' => $this->assetAuditTitle($auditEvent),
            default => __('Ereignis'),
        };

        $detail = match ($kind) {
            'order.linked' => (string) ($payload['title'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'protocol.linked' => (string) ($payload['title'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'material.linked' => (string) ($payload['description'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'attachment.linked' => (string) ($payload['name'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'asset.audit' => $this->assetAuditDetail($payload),
            default => __('Unbekanntes Ereignis'),
        };

        return [
            'kind' => $kind,
            'title' => $title,
            'detail' => $detail,
            'occurred_at_formatted' => $this->formatTimelineDate($event['occurred_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assetAuditDetail(array $payload): string {
        $name = trim((string) ($payload['user_name'] ?? ''));

        return $name !== '' ? __('durch :name', ['name' => $name]) : '';
    }

    private function assetAuditTitle(string $auditEvent): string {
        return match ($auditEvent) {
            'asset.statusChanged' => __('Status geändert'),
            'asset.decommissioned' => __('Außer Betrieb gesetzt'),
            'asset.ownershipTransferred' => __('Eigentum übertragen'),
            'asset.moved' => __('Standort geändert'),
            'asset.updated' => __('Asset aktualisiert'),
            'asset.created' => __('Asset angelegt'),
            'created' => __('Datensatz angelegt'),
            'updated' => __('Datensatz geändert'),
            'deleted' => __('Datensatz gelöscht'),
            default => __('Asset-Ereignis'),
        };
    }

    private function formatTimelineDate(mixed $value): string {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        return Carbon::parse($value)->format('d.m.Y H:i');
    }

    private function normalizeAssetClass(string $value): ?string {
        return array_key_exists($value, $this->assetClassOptions()) ? $value : null;
    }

    private function normalizeAssetStatus(string $value): ?string {
        return array_key_exists($value, $this->assetStatusOptions()) ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private function customerOptions(): array {
        return Customer::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ForeignCustomer>
     */
    private function foreignCustomerOptions(): \Illuminate\Support\Collection {
        return ForeignCustomer::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array {
        /** @var array<string, string> $pool */
        $pool = (array) config('asset_categories', []);

        return $pool;
    }

    /**
     * Liefert die Sammlungen für den Facility-Picker.
     *
     * @return array{sites: \Illuminate\Support\Collection<int, Site>, buildings: \Illuminate\Support\Collection<int, Building>, floors: \Illuminate\Support\Collection<int, Floor>, rooms: \Illuminate\Support\Collection<int, Room>}
     */
    private function facilityData(): array {
        return [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
            'buildings' => Building::query()->orderBy('name')->get(['id', 'name', 'site_id']),
            'floors' => Floor::query()->orderBy('level')->get(['id', 'label', 'level', 'building_id']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name', 'floor_id', 'customer_id']),
        ];
    }

    /**
     * Leitet die Picker-Vorbelegung (Customer/Site/Building/Floor/Room) aus den
     * Query-Parametern ab. Akzeptiert ?room=, ?floor=, ?building=, ?site=
     * oder ?customer=; höhere Ebenen werden aufgefüllt.
     *
     * @return array{customer_id: int|null, foreign_customer_id: int|null, site_id: int|null, building_id: int|null, floor_id: int|null, room_id: int|null}
     */
    private function resolvePrefill(Request $request): array {
        $rawRoom = (string) $request->query('room', '');
        $rawFloor = (string) $request->query('floor', '');
        $rawBuilding = (string) $request->query('building', '');
        $rawSite = (string) $request->query('site', '');
        $rawCustomer = (string) $request->query('customer', '');

        $roomId = Sqid::decodeOrNumeric(Room::class, $rawRoom);
        $floorId = Sqid::decodeOrNumeric(Floor::class, $rawFloor);
        $buildingId = Sqid::decodeOrNumeric(Building::class, $rawBuilding);
        $siteId = Sqid::decodeOrNumeric(Site::class, $rawSite);
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomer);

        if ($roomId !== null) {
            $room = Room::query()->with('floorRelation.building.site')->find($roomId);
            if ($room !== null) {
                $floorId ??= $room->floor_id;
                $buildingId ??= $room->floorRelation?->building_id;
                $siteId ??= $room->floorRelation?->building?->site_id;
                $customerId ??= $room->customer_id ?? $room->floorRelation?->building?->site?->customer_id;
            }
        }
        if ($floorId !== null) {
            $floor = Floor::query()->with('building.site')->find($floorId);
            if ($floor !== null) {
                $buildingId ??= $floor->building_id;
                $siteId ??= $floor->building?->site_id;
                $customerId ??= $floor->building?->site?->customer_id;
            }
        }
        if ($buildingId !== null && $siteId === null) {
            $building = Building::query()->with('site')->find($buildingId);
            if ($building !== null) {
                $siteId = $building->site_id;
                $customerId ??= $building->site?->customer_id;
            }
        }
        if ($siteId !== null && $customerId === null) {
            $customerId = Site::query()->whereKey($siteId)->value('customer_id');
        }

        return [
            'customer_id' => $customerId,
            'foreign_customer_id' => null,
            'site_id' => $siteId,
            'building_id' => $buildingId,
            'floor_id' => $floorId,
            'room_id' => $roomId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function assetClassOptions(): array {
        return [
            AssetClass::Device->value => __('Gerät'),
            AssetClass::Machine->value => __('Maschine'),
            AssetClass::Tool->value => __('Werkzeug'),
            AssetClass::Vehicle->value => __('Fahrzeug'),
            AssetClass::Installation->value => __('Installation'),
            AssetClass::Software->value => __('Software'),
            AssetClass::Other->value => __('Sonstiges'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function assetStatusOptions(): array {
        return [
            AssetStatus::Active->value => __('Aktiv'),
            AssetStatus::InMaintenance->value => __('In Wartung'),
            AssetStatus::InRepair->value => __('In Reparatur'),
            AssetStatus::Blocked->value => __('Gesperrt'),
            AssetStatus::Reserved->value => __('Reserviert'),
            AssetStatus::LoanOut->value => __('Ausgeliehen'),
            AssetStatus::Replaced->value => __('Ersetzt'),
            AssetStatus::Decommissioned->value => __('Außer Betrieb'),
            AssetStatus::Lost->value => __('Verloren'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function assetStatusOptionsForCreate(): array {
        return [
            AssetStatus::Active->value => __('Aktiv'),
            AssetStatus::InMaintenance->value => __('In Wartung'),
            AssetStatus::InRepair->value => __('In Reparatur'),
            AssetStatus::Blocked->value => __('Gesperrt'),
            AssetStatus::Reserved->value => __('Reserviert'),
            AssetStatus::LoanOut->value => __('Ausgeliehen'),
            AssetStatus::Replaced->value => __('Ersetzt'),
            AssetStatus::Lost->value => __('Verloren'),
        ];
    }
}
