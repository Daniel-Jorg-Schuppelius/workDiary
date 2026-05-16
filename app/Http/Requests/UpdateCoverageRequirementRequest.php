<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCoverageRequirementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCoverageRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shift_type_id' => ['required', 'integer', 'exists:shift_types,id'],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'specific_date' => ['nullable', 'date'],
            'min_staff' => ['required', 'integer', 'min:0', 'max:99'],
            'max_staff' => ['nullable', 'integer', 'min:0', 'max:99', 'gte:min_staff'],
            'required_qualification_ids' => ['nullable', 'array'],
            'required_qualification_ids.*' => ['integer', 'exists:qualifications,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
