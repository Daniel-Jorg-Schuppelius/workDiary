<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateScheduledShiftRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksShiftCompliance;
use App\Models\ScheduledShift;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateScheduledShiftRequest extends FormRequest
{
    use ChecksShiftCompliance;

    public function authorize(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'shift_type_id' => ['sometimes', 'nullable', 'integer', 'exists:shift_types,id'],
            'duty_plan_id' => ['sometimes', 'nullable', 'integer', 'exists:duty_plans,id'],
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ScheduledShift::$statuses)],
            'override_compliance' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var ScheduledShift|null $shift */
        $shift = $this->route('shift');
        $this->attachComplianceCheck($validator, $shift instanceof ScheduledShift ? $shift : null);
    }
}
