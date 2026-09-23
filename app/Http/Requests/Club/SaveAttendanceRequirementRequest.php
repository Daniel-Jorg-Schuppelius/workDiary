<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAttendanceRequirementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubEventKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubDepartment, ClubGroup};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveAttendanceRequirementRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_group_id' => ClubGroup::class, 'club_department_id' => ClubDepartment::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'club_group_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_groups')],
            'club_department_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_departments')],
            'required_count' => ['required', 'integer', 'min:1', 'max:999'],
            'period_months' => ['required', 'integer', 'min:1', 'max:120'],
            'event_kind' => ['nullable', 'string', Rule::enum(ClubEventKind::class)],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
