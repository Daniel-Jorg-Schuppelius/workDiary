<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AcceptMatchProposalRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Facility\Room;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;

class AcceptMatchProposalRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['leader_user_id' => User::class, 'room_id' => Room::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'opponent_name' => ['required', 'string', 'max:150'],
            'competition' => ['nullable', 'string', 'max:120'],
            'is_home' => ['sometimes', 'boolean'],
            'venue' => ['nullable', 'string', 'max:200'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
        ];
    }
}
