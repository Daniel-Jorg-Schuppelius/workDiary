<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMatchResultRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;

class SaveMatchResultRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'home' => ['nullable', 'integer', 'min:0', 'max:999'],
            'away' => ['nullable', 'integer', 'min:0', 'max:999'],
            'periods' => ['nullable', 'array', 'max:12'],
            'periods.*.home' => ['nullable', 'integer', 'min:0', 'max:999'],
            'periods.*.away' => ['nullable', 'integer', 'min:0', 'max:999'],
            'result_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
