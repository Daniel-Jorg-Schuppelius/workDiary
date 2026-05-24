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

use App\Enums\Asset\AssetClass;
use App\Enums\Asset\AssetStatus;
use App\Models\Asset;
use Illuminate\Http\Request;
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

    private function normalizeAssetClass(string $value): ?string {
        return array_key_exists($value, $this->assetClassOptions()) ? $value : null;
    }

    private function normalizeAssetStatus(string $value): ?string {
        return array_key_exists($value, $this->assetStatusOptions()) ? $value : null;
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
}
