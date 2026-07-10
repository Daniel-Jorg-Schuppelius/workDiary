<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTeamRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\User;

class SaveTeamRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'lead_user_id' => User::class,
        'member_ids' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:16'],
            'lead_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization()],
        ];
    }
}
