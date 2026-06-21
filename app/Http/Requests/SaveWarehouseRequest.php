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

use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends BaseFormRequest {
    /** @var list<string> */
    private const FLAGS = ['is_default', 'active', 'blocked'];

    protected function prepareForValidation(): void {
        $merge = [];
        foreach (self::FLAGS as $flag) {
            $merge[$flag] = $this->boolean($flag);
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
            'location_note' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'active' => ['boolean'],
            'blocked' => ['boolean'],
        ];
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
