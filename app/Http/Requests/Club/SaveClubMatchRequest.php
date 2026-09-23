<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubMatchRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubGroup, ClubSeason};
use App\Models\Platform\User;
use App\Models\Room;
use App\Rules\ExistsInCurrentOrganization;

class SaveClubMatchRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_group_id' => ClubGroup::class,
        'club_season_id' => ClubSeason::class,
        'club_group_ids' => ClubGroup::class,
        'leader_user_id' => User::class,
        'room_id' => Room::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $isEdit = $this->route('event') !== null;

        return [
            'club_group_id' => [$isEdit ? 'prohibited' : 'required', 'integer', new ExistsInCurrentOrganization('club_groups')],
            'club_season_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_seasons')],
            'club_group_ids' => ['nullable', 'array', 'max:20'],
            'club_group_ids.*' => ['integer', new ExistsInCurrentOrganization('club_groups')],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'opponent_name' => ['required', 'string', 'max:150'],
            'competition' => ['nullable', 'string', 'max:120'],
            'is_home' => ['sometimes', 'boolean'],
            'venue' => ['nullable', 'string', 'max:200'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'meet_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
        ];
    }
}
