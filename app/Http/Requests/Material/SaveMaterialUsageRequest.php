<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaterialUsageRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Material;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Material\Material;
use App\Models\Material\MaterialUsage;
use App\Models\Time\Timesheet;
use Illuminate\Validation\Validator;
use App\Http\Requests\BaseFormRequest;

class SaveMaterialUsageRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'material_id' => \App\Models\Material\Material::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'material_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('materials')],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999.999'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Zeilen- und Zettelsumme sind decimal(12,2): Menge × Preis (auch der aus dem
     * Materialstamm übernommene) darf sie nicht sprengen (UI-Fuzz 2026-09-21: 1264).
     */
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            $timesheet = $this->route('timesheet');
            if (! $timesheet instanceof Timesheet || $v->errors()->hasAny(['quantity', 'unit_price', 'material_id'])) {
                return;
            }

            $price = $this->input('unit_price');
            if (! is_numeric($price) && $this->filled('material_id')) {
                $price = Material::query()->whereKey((int) $this->input('material_id'))->value('default_unit_price');
            }
            $usage = $this->route('usage');
            $usageId = $usage instanceof MaterialUsage ? $usage->getKey() : null;
            $others = $timesheet->materialUsages()
                ->when($usageId !== null, fn ($q) => $q->whereKeyNot($usageId))
                ->sum('line_total_net');

            if ((float) $this->input('quantity') * (float) $price + (float) $others > 9999999999.99) {
                $v->errors()->add('quantity', __('Menge × Einzelpreis übersteigt die höchste speicherbare Summe des Stundenzettels.'));
            }
        });
    }
}
