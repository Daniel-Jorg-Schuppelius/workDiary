<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveExamOfferRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubEventVisibility;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubDepartment, ClubGradingSystem};
use App\Models\Platform\User;
use App\Models\Room;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Prüfungsangebot (MVP-847): Termindaten wie beim Vereinstermin plus Ordnung, Zielgrade und Prüfer; Listen-Sqids dekodiert der Controller. */
class SaveExamOfferRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_grading_system_id' => ClubGradingSystem::class,
        'club_department_id' => ClubDepartment::class,
        'leader_user_id' => User::class,
        'room_id' => Room::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'club_grading_system_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_grading_systems')],
            'target_grade_ids' => ['required', 'array', 'min:1'],
            'target_grade_ids.*' => ['string', 'max:64'],
            'examiner_user_ids' => ['nullable', 'array'],
            'examiner_user_ids.*' => ['string', 'max:64'],
            'visibility' => ['required', 'string', Rule::enum(ClubEventVisibility::class)],
            'club_group_ids' => ['nullable', 'array'],
            'club_group_ids.*' => ['string', 'max:64'],
            'club_department_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_departments')],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'leader_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'registration_lead_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellation_lead_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
