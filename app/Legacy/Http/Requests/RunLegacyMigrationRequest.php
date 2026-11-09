<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunLegacyMigrationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Platform\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunLegacyMigrationRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['organization' => Organization::class];

    public function authorize(): bool {
        return $this->user()?->isGlobalAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'type' => ['required', 'string', 'in:all,users,diary,shifts,assignments,support'],
            // Kunden und Tickets des Support-Portals gehören einer Organisation.
            'organization' => ['nullable', 'required_if:type,support', 'integer', Rule::exists('organizations', 'id')],
        ];
    }
}
