<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreWorkDelegationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Http\Requests\BaseFormRequest;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Illuminate\Validation\Rule;

/**
 * Vertretung anlegen (Feature 202, MVP-1647). Personen kommen als Sqid und
 * müssen zur aktiven Organisation gehören; wer für wen anlegen darf, prüft der
 * Controller über die Policy, die fachlichen Regeln (Überschneidung, Kette)
 * der Dienst.
 */
class StoreWorkDelegationRequest extends BaseFormRequest {
    protected function prepareForValidation(): void {
        $sqid = fn (string $field): ?int => is_scalar($value = $this->input($field)) ? Sqid::decodeOrNumeric(User::class, (string) $value) : null;
        $this->merge([
            'represented_user_id' => $sqid('represented_user_id'),
            'deputy_user_id' => $sqid('deputy_user_id'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'represented_user_id' => ['required', 'integer', new ExistsInCurrentOrganization()],
            'deputy_user_id' => ['required', 'integer', 'different:represented_user_id', new ExistsInCurrentOrganization()],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'kind' => ['required', Rule::enum(WorkDelegationKind::class)],
            'absence' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
