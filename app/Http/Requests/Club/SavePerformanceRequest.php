<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePerformanceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubSportProfile;
use App\Models\Event;
use App\Rules\ExistsInCurrentOrganization;

class SavePerformanceRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_sport_profile_id' => ClubSportProfile::class, 'event_id' => Event::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        $isEdit = $this->route('performance') !== null;
        // Ergebnis zu einer Wettkampfmeldung: Profil, Disziplin und Datum kommen aus der Meldung.
        $viaEntry = $this->route('entry') !== null;
        $identity = $isEdit ? 'prohibited' : ($viaEntry ? 'nullable' : 'required');

        return [
            'club_sport_profile_id' => [$identity, 'integer', new ExistsInCurrentOrganization('club_sport_profiles')],
            'discipline_code' => [$identity, 'string', 'max:30'],
            'event_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('events')],
            'performed_on' => [$isEdit ? 'prohibited' : 'nullable', 'date'],
            'value' => ['required', 'string', 'max:20'],
            'placement' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'note' => ['nullable', 'string', 'max:255'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }
}
