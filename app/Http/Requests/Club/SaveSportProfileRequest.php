<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSportProfileRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\{ClubResultFormat, ClubSportFamily};
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveSportProfileRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'family' => ['required', 'string', Rule::enum(ClubSportFamily::class)],
            'positions' => ['nullable', 'string', 'max:4000'],
            'squad_size_field' => ['nullable', 'integer', 'min:1', 'max:99'],
            'squad_size_bench' => ['nullable', 'integer', 'min:0', 'max:99'],
            'result_format' => ['required', 'string', Rule::enum(ClubResultFormat::class)],
            'has_doubles' => ['sometimes', 'boolean'],
            'age_cutoff' => ['nullable', 'string', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'disciplines' => ['nullable', 'string', 'max:4000'],
            'resource_types' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
