<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCompetitionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubEventVisibility;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubGroup, ClubSportProfile};
use App\Models\Platform\User;
use App\Models\Room;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveCompetitionRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_sport_profile_id' => ClubSportProfile::class, 'club_group_ids' => ClubGroup::class, 'leader_user_id' => User::class, 'room_id' => Room::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        $isEdit = $this->route('event') !== null;

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'club_sport_profile_id' => [$isEdit ? 'prohibited' : 'required', 'integer', new ExistsInCurrentOrganization('club_sport_profiles')],
            'disciplines' => ['required', 'array', 'min:1', 'max:50'],
            'disciplines.*' => ['string', 'max:30'],
            'visibility' => ['nullable', 'string', Rule::enum(ClubEventVisibility::class)],
            'club_group_ids' => ['nullable', 'array', 'max:50'],
            'club_group_ids.*' => ['integer', new ExistsInCurrentOrganization('club_groups')],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'registration_lead_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'venue' => ['nullable', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'entry_fee' => ['nullable', 'string', 'max:20'],
            'requires_start_right' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
