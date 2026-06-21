<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSupplierRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Concerns\PartyFormFields;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use PartyFormFields;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'tag_ids' => \App\Models\Tag::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');
        $organizationId = $supplier instanceof Supplier
            ? $supplier->organization_id
            : $this->currentOrganizationId();

        return array_merge($this->partyBaseRules(), [
            'number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('suppliers', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($supplier?->id),
            ],
            'vendor_number' => ['nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function prepareForValidation(): void {
        $this->merge(array_merge($this->partyNormalizedData(), [
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]));
    }
}
