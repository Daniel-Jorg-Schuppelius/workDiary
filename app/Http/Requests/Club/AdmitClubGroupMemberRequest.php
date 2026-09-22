<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdmitClubGroupMemberRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Rules\ExistsInCurrentOrganization;

/** Aufnahme in eine Gruppe bzw. Antrag (MVP-842); Ausnahme nur mit Begründung. */
class AdmitClubGroupMemberRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_member_id' => ClubMember::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'valid_from' => ['required', 'date'],
            'mode' => ['nullable', 'string', 'in:admit,request'],
            'override' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:override,1'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['override' => $this->boolean('override')]);
    }
}
