<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubGuardianRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubGuardianPermission;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{ContactSatelliteFields, DecodesSqidInputs};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Vertretung eines Mitglieds (MVP-842): Kontakt, optionales Konto, erlaubte Handlungen, Gültigkeit. */
class SaveClubGuardianRequest extends BaseFormRequest {
    use ContactSatelliteFields;
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return $this->addressRules() + [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::enum(ClubGuardianPermission::class)],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
