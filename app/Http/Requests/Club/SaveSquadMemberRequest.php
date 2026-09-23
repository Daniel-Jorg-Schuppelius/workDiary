<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSquadMemberRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Rules\ExistsInCurrentOrganization;

/** Kaderzuordnung: vorhandenes Mitglied oder neuer Gastspieler mit Herkunftsverein. */
class SaveSquadMemberRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_member_id' => ClubMember::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        $isEdit = $this->route('squadMember') !== null;

        return [
            'club_member_id' => [$isEdit ? 'prohibited' : 'nullable', 'integer', new ExistsInCurrentOrganization('club_members')],
            'guest_first_name' => [$isEdit ? 'prohibited' : 'required_without:club_member_id', 'nullable', 'string', 'max:120'],
            'guest_last_name' => [$isEdit ? 'prohibited' : 'required_without:club_member_id', 'nullable', 'string', 'max:120'],
            'guest_birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'guest_origin' => ['nullable', 'string', 'max:120'],
            'valid_from' => [$isEdit ? 'prohibited' : 'nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'jersey_no' => ['nullable', 'integer', 'min:0', 'max:999'],
            'position_code' => ['nullable', 'string', 'max:30'],
            'strength_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
