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
use App\Models\{Asset, Attachment, Customer, DiaryEntry, MaterialUsage, Protocol, User};
use App\Services\Asset\AssetService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssetController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Asset::class);

        $query = trim($request->string('q')->toString());
        $classFilter = $this->normalizeAssetClass($request->string('class')->toString());
        $statusFilter = $this->normalizeAssetStatus($request->string('status')->toString());

        $assetsQuery = Asset::query()
            ->with(['customer:id,name'])
            ->latest('updated_at');

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

        return view('assets.index', [
            'assets' => $assets,
            'classOptions' => $classOptions,
            'statusOptions' => $statusOptions,
            'canCreate' => Gate::allows('create', Asset::class),
            'activeFilters' => [
                'q' => $query,
                'class' => $classFilter ?? 'all',
                'status' => $statusFilter ?? 'all',
            ],
            'activeFilterChips' => array_values(array_filter([
                $query !== '' ? __('Suche: :value', ['value' => $query]) : null,
                $classFilter !== null ? __('Typ: :value', ['value' => $classOptions[$classFilter] ?? $classFilter]) : null,
                $statusFilter !== null ? __('Status: :value', ['value' => $statusOptions[$statusFilter] ?? $statusFilter]) : null,
            ])),
            'hasActiveFilters' => $query !== '' || $classFilter !== null || $statusFilter !== null,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Asset::class);

        return view('assets._form_dialog', [
            'asset' => new Asset(['status' => AssetStatus::Active->value]),
            'classOptions' => $this->assetClassOptions(),
            'statusOptions' => $this->assetStatusOptionsForCreate(),
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(SaveAssetRequest $request, AssetService $assetService): RedirectResponse {
        Gate::authorize('create', Asset::class);
        $user = $request->user();

        if (! $user instanceof \App\Models\User) {
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

    public function show(Asset $asset, Request $request): View {
        Gate::authorize('view', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $asset->load(['customer:id,name']);
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

        return view('assets.show', [
            'asset' => $asset,
            'classOptions' => $this->assetClassOptions(),
            'statusOptions' => $this->assetStatusOptions(),
            'diaryEntries' => $diaryEntries,
            'protocols' => $protocols,
            'materialUsages' => $materialUsages,
            'attachments' => $attachments,
            'visibleCounts' => [
                'diary' => $diaryEntries->count(),
                'protocols' => $protocols->count(),
                'material' => $materialUsages->count(),
                'attachments' => $attachments->count(),
            ],
        ]);
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
