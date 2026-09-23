<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubEventRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\{ClubEventKind, ClubEventVisibility};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubDepartment, ClubGroup};
use App\Models\Platform\User;
use App\Models\Room;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Vereinstermin (MVP-843): Zeiten als Wandzeit der Zeitzone, Umrechnung nach UTC im Controller. */
class SaveClubEventRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_department_id' => ClubDepartment::class,
        'leader_user_id' => User::class,
        'room_id' => Room::class,
        'club_group_ids' => ClubGroup::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', 'string', Rule::enum(ClubEventKind::class)],
            'visibility' => ['required', 'string', Rule::enum(ClubEventVisibility::class)],
            'club_department_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_departments')],
            'discipline' => ['nullable', 'string', 'max:60'],
            'club_group_ids' => ['nullable', 'array', 'max:50'],
            'club_group_ids.*' => ['integer', new ExistsInCurrentOrganization('club_groups')],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'registration_lead_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellation_lead_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'recurrence' => ['nullable', 'string', 'in:none,weekly,biweekly,monthly'],
            'series_until' => ['nullable', 'date', 'after:started_at'],
            'scope' => ['nullable', 'string', 'in:this,future'],
        ];
    }
}
