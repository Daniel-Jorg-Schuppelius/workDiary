<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveNumberFormatRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Numbering\NumberScope;
use Illuminate\Validation\Rule;

class SaveNumberFormatRequest extends BaseFormRequest {
    /** @return array<string, array<int, mixed>> */
    public function rules(): array {
        return [
            'scope' => ['required', Rule::enum(NumberScope::class)],
            'prefix' => ['nullable', 'string', 'max:16', 'regex:/^[A-Z0-9-]*$/i'],
            'prefix_separator' => ['nullable', 'string', 'max:4'],
            'include_year' => ['sometimes', 'boolean'],
            'year_separator' => ['nullable', 'string', 'max:4'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'reset_per_year' => ['sometimes', 'boolean'],
            'starts_at' => ['required', 'integer', 'min:0'],
        ];
    }
}
