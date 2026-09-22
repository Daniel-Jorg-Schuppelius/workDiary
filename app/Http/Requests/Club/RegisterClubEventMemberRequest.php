<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RegisterClubEventMemberRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Rules\ExistsInCurrentOrganization;

/** Mitglied anmelden oder einladen (MVP-843); spontan = ohne Zielgruppen- und Fristprüfung. */
class RegisterClubEventMemberRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_member_id' => ClubMember::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'mode' => ['nullable', 'string', 'in:register,invite'],
            'spontaneous' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['spontaneous' => $this->boolean('spontaneous')]);
    }
}
