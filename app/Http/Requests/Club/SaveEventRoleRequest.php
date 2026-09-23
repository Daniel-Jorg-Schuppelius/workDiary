<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveEventRoleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubEventRoleKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveEventRoleRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_member_id' => ClubMember::class, 'user_id' => User::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'role' => ['required', 'string', Rule::enum(ClubEventRoleKind::class)],
            'club_member_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_members')],
            'user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'name' => ['nullable', 'string', 'max:120', 'required_without_all:club_member_id,user_id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
