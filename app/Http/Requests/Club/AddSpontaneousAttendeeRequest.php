<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddSpontaneousAttendeeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Rules\ExistsInCurrentOrganization;

/** Spontane Teilnahme durch die Leitung (MVP-844). */
class AddSpontaneousAttendeeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_member_id' => ClubMember::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
