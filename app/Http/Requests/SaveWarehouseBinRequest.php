<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveWarehouseBinRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\{Warehouse, WarehouseBin};
use Illuminate\Validation\Rule;

/** Lagerplatz anlegen/bearbeiten (Feature 048, MVP-706); Kürzel je Lager eindeutig. */
class SaveWarehouseBinRequest extends BaseFormRequest {
    protected function prepareForValidation(): void {
        $this->merge([
            'active' => $this->boolean('active'),
            'blocked' => $this->boolean('blocked'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Warehouse|null $warehouse */
        $warehouse = $this->route('warehouse');
        /** @var WarehouseBin|null $bin */
        $bin = $this->route('bin');

        return [
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('warehouse_bins', 'code')
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouse?->id))
                    ->ignore($bin?->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'active' => ['boolean'],
            'blocked' => ['boolean'],
        ];
    }

    /**
     * Validierte Felder mit sort_order als Integer (leer → 0).
     *
     * @return array<string, mixed>
     */
    public function binData(): array {
        $data = $this->validated();
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
