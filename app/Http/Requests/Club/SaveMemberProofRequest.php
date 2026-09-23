<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMemberProofRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubProofKind;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Lehrgangs- oder externer Trainingsnachweis (MVP-846). */
class SaveMemberProofRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'kind' => ['required', 'string', Rule::enum(ClubProofKind::class)],
            'label' => ['required', 'string', 'max:120'],
            'discipline' => ['nullable', 'string', 'max:60'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sessions' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'obtained_on' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:obtained_on'],
            'origin' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
