<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreShiftTypeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreShiftTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:5'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_start_time' => ['nullable', 'date_format:H:i'],
            'default_end_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
