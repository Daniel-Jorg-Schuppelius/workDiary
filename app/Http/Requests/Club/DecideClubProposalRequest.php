<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecideClubProposalRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubGroup;
use App\Rules\ExistsInCurrentOrganization;

/** Wechselvorschlag bestätigen (MVP-842): Wirksamkeitsdatum, optionale Zielgruppe, Ausnahme mit Begründung. */
class DecideClubProposalRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'suggested_group_id' => ClubGroup::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'effective_on' => ['required', 'date'],
            'suggested_group_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_groups')],
            'override' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:override,1'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['override' => $this->boolean('override')]);
    }
}
