<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveFeeAccountRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Customer\Customer;
use App\Rules\ExistsInCurrentOrganization;

/** Beitragskonto (MVP-849): bestehender Kunde oder neuer Debitor. */
class SaveFeeAccountRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'name' => ['required_without:customer_id', 'nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
