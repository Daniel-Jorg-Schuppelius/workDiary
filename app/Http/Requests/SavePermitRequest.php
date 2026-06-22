<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePermitRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Permit\PermitStatus;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rules\Enum;

class SavePermitRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'event_id' => \App\Models\Event::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'event_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('events', 'id')],
            'title' => ['required', 'string', 'max:200'],
            'permit_type' => ['nullable', 'string', 'max:60'],
            'authority' => ['nullable', 'string', 'max:200'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'status' => ['required', new Enum(PermitStatus::class)],
            'applied_at' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
