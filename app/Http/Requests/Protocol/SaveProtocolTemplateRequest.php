<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProtocolTemplateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Rules\ExistsInCurrentOrganization;

/** Name, Zuordnung und Gültigkeit einer Protokollvorlage (MVP-901). */
class SaveProtocolTemplateRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['entry_type_id' => EntryType::class, 'customer_id' => Customer::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'entry_type_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('entry_types')],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
        ];
    }
}
