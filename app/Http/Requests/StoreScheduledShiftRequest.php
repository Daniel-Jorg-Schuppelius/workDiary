<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreScheduledShiftRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Http\Requests\Concerns\{ChecksShiftCompliance, DecodesSqidInputs};
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\{Rule, Validator};

class StoreScheduledShiftRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\User::class,
        'shift_type_id' => \App\Models\ShiftType::class,
        'duty_plan_id' => \App\Models\DutyPlan::class,
    ];

    use ChecksShiftCompliance;

    public function authorize(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
            'duty_plan_id' => ['nullable', 'integer', 'exists:duty_plans,id'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(ScheduledShiftStatus::class)],
            'override_compliance' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $this->attachComplianceCheck($validator);
    }
}
