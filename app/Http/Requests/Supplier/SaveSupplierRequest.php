<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSupplierRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Supplier;

use App\Enums\Numbering\NumberScope;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, PartyFormFields};
use App\Models\Supplier\Supplier;
use App\Services\Numbering\NumberAuthority;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use PartyFormFields;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'tag_ids' => \App\Models\Classification\Tag::class,
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
                // Vergibt ein Buchhaltungsprogramm die Nummer, ist das Feld gesperrt (MVP-1134).
                Rule::prohibitedIf(fn (): bool => app(NumberAuthority::class)->numberLockedBy(NumberScope::Supplier, $organizationId, $supplier?->number_source) !== null),
                'nullable',
                'string',
                'max:64',
                Rule::unique('suppliers', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($supplier?->id),
            ],
            'vendor_number' => ['nullable', 'string', 'max:64'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            // Gutschriftsverfahren (MVP-1280): Datum der Vereinbarung nach § 14 Abs. 2 Satz 2 UStG.
            'self_billing_agreed_on' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'exclude_from_reports' => ['sometimes', 'boolean'],
        ]);
    }

    protected function prepareForValidation(): void {
        $this->merge(array_merge($this->partyNormalizedData(), [
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'exclude_from_reports' => $this->boolean('exclude_from_reports'),
        ]));
    }
}
