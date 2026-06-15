<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDesiredShiftRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Shift\ShiftPreference;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{DesiredShift, ShiftType, User};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

class SaveDesiredShiftRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'shift_type_id' => ShiftType::class,
    ];

    public function authorize(): bool {
        if (! Auth::user() instanceof User) {
            return false;
        }
        $desired = $this->route('desired');

        return $desired instanceof DesiredShift
            ? Gate::allows('update', $desired)
            : Gate::allows('create', DesiredShift::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'date' => ['required', 'date'],
            'shift_type_id' => ['nullable', 'integer', 'exists:shift_types,id'],
            'preference' => ['required', Rule::enum(ShiftPreference::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
