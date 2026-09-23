<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveGradeRequirementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\{ClubCountingBasis, ClubEventKind};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubGrade;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Voraussetzungen je Zielgrad (MVP-846); Gruppen-Sqids werden im Controller dekodiert. */
class SaveGradeRequirementRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'previous_grade_id' => ClubGrade::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'previous_grade_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_grades')],
            'min_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'min_sessions' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'min_minutes_per_session' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'counting_basis' => ['required', 'string', Rule::enum(ClubCountingBasis::class)],
            'window_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'wait_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'counted_event_kinds' => ['nullable', 'array'],
            'counted_event_kinds.*' => ['string', Rule::enum(ClubEventKind::class)],
            'counted_group_ids' => ['nullable', 'array'],
            'counted_group_ids.*' => ['string', 'max:64'],
            'required_proof_label' => ['nullable', 'string', 'max:120'],
            'requires_approval' => ['sometimes', 'boolean'],
            'allows_exception' => ['sometimes', 'boolean'],
        ];
    }
}
