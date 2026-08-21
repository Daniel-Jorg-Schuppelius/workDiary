<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveGuaranteeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeKind};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Bürgschaft anlegen/ändern (Feature 114, MVP-603).
 *
 * Kontakte kommen als Sqid; die Existenzprüfung ist org-gescopt — ein rohes
 * `exists:` auf org-geführte Tabellen wäre ein Mandanten-Leck.
 * Die Berechtigung trägt der Controller (Abrechnungsrecht).
 */
class SaveGuaranteeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'issuer_supplier_id' => \App\Models\Supplier::class,
        'customer_id' => \App\Models\Customer::class,
        'supplier_id' => \App\Models\Supplier::class,
        'project_id' => \App\Models\Project::class,
        'responsible_user_id' => \App\Models\User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'direction' => ['required', Rule::enum(GuaranteeDirection::class)],
            'kind' => ['required', Rule::enum(GuaranteeKind::class)],
            'reference' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'issuer_name' => ['nullable', 'string', 'max:191'],
            'issuer_supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
