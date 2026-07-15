<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignCrisisTeamRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;

/**
 * Validierung für die Benennung eines Stabsmitglieds (MVP-213).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class AssignCrisisTeamRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'crisis_role_id' => \App\Models\Crisis\CrisisRole::class,
        'user_id' => \App\Models\User::class,
        'deputy_user_id' => \App\Models\User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'crisis_role_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('crisis_roles')],
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'deputy_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users'), 'different:user_id'],
            'contact_note' => ['nullable', 'string', 'max:300'],
        ];
    }
}
