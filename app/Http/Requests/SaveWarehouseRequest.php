<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveWarehouseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Inventory\WarehouseKind;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Site, Team, Vehicle, Warehouse};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var list<string> */
    private const FLAGS = ['is_default', 'active', 'blocked'];

    /** @var array<string, class-string> Bezug (MVP-706) kommt als Sqid aus dem Formular. */
    protected array $sqidFields = [
        'site_id' => Site::class,
        'vehicle_id' => Vehicle::class,
        'team_id' => Team::class,
    ];

    protected function prepareForValidation(): void {
        $merge = [];
        foreach (self::FLAGS as $flag) {
            $merge[$flag] = $this->boolean($flag);
        }
        // Art ist optional (Altaufrufer ohne Feld bleiben feste Lager).
        if (! $this->filled('kind')) {
            $merge['kind'] = WarehouseKind::Fixed->value;
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Warehouse|null $warehouse */
        $warehouse = $this->route('warehouse');
        $organizationId = $warehouse instanceof Warehouse
            ? $warehouse->organization_id
            : $this->currentOrganizationId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('warehouses', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $organizationId))
                    ->ignore($warehouse?->id),
            ],
            'kind' => ['required', Rule::enum(WarehouseKind::class)],
            'site_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('sites')],
            'vehicle_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('vehicles')],
            'team_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('teams')],
            'location_note' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'active' => ['boolean'],
            'blocked' => ['boolean'],
        ];
    }

    /**
     * Validierte Daten mit konsistentem Bezug: nur die Spalte der gewählten
     * Art bleibt gesetzt (ein Fahrzeuglager trägt keinen Standort).
     *
     * @return array<string, mixed>
     */
    public function warehouseData(): array {
        $data = $this->validated();
        $kind = WarehouseKind::from((string) $data['kind']);
        foreach (['site_id', 'vehicle_id', 'team_id'] as $column) {
            $data[$column] = $kind->referenceColumn() === $column ? ($data[$column] ?? null) : null;
        }

        return $data;
    }

    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof \App\Models\Organization) {
                return (int) $organization->id;
            }
        }

        $user = \Illuminate\Support\Facades\Auth::user();

        return $user?->organization_id !== null ? (int) $user->organization_id : null;
    }
}
