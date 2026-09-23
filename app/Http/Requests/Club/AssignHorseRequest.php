<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignHorseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubHorse, ClubMember};
use App\Rules\ExistsInCurrentOrganization;

/** Reiter–Pferd je Stunde: Pferd oder ausdrücklich eigenes Pferd; Ausnahme nur mit Begründung. */
class AssignHorseRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_member_id' => ClubMember::class, 'club_horse_id' => ClubHorse::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'club_horse_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_horses'), 'required_without:own_horse'],
            'own_horse' => ['sometimes', 'boolean'],
            'override' => ['sometimes', 'boolean'],
            'override_note' => ['nullable', 'string', 'max:255', 'required_if:override,1'],
        ];
    }
}
