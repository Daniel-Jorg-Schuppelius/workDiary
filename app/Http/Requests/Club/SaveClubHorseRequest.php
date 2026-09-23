<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubHorseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubHorseKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubGroup, ClubMember};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveClubHorseRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['owner_member_id' => ClubMember::class, 'group_ids' => ClubGroup::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::enum(ClubHorseKind::class)],
            'owner_member_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_members')],
            'contact' => ['nullable', 'string', 'max:190'],
            'max_uses_per_day' => ['nullable', 'integer', 'min:1', 'max:20'],
            'rest_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'requires_clearance' => ['sometimes', 'boolean'],
            'suitable_for' => ['nullable', 'string', 'max:255'],
            'group_ids' => ['nullable', 'array', 'max:50'],
            'group_ids.*' => ['integer', new ExistsInCurrentOrganization('club_groups')],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
