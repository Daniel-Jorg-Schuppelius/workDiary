<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAvailabilityWindowRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Shift\AvailabilityKind;
use App\Models\{AvailabilityWindow, User};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

class SaveAvailabilityWindowRequest extends FormRequest {
    public function authorize(): bool {
        if (! Auth::user() instanceof User) {
            return false;
        }
        $window = $this->route('window');

        return $window instanceof AvailabilityWindow
            ? Gate::allows('update', $window)
            : Gate::allows('create', AvailabilityWindow::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'weekday' => ['nullable', 'integer', 'between:0,6', 'required_without:specific_date'],
            'specific_date' => ['nullable', 'date', 'required_without:weekday'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'kind' => ['required', Rule::enum(AvailabilityKind::class)],
            'priority' => ['nullable', 'integer', 'between:1,3'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
