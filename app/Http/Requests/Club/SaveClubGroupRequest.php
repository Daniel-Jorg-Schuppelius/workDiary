<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubGroupRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubAdmissionMode;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubDepartment;
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Vereinsgruppe (MVP-842): Stammdaten, Leitung, Obergrenze, Aufnahmemodus und inklusive Altersgrenzen. */
class SaveClubGroupRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_department_id' => ClubDepartment::class,
        'leader_user_id' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'club_department_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_departments')],
            'description' => ['nullable', 'string', 'max:2000'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'admission_mode' => ['required', 'string', Rule::enum(ClubAdmissionMode::class)],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'criteria_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }
}
