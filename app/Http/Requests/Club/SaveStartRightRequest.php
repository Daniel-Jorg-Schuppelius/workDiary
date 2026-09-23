<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveStartRightRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubSportProfile;
use App\Rules\ExistsInCurrentOrganization;

class SaveStartRightRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_sport_profile_id' => ClubSportProfile::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_sport_profile_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_sport_profiles')],
            'reference' => ['nullable', 'string', 'max:120'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
