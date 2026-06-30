<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveSoftwareRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveSoftwareRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        $orgId = $this->user()?->organization_id;
        $routeSoftware = $this->route('software');
        $softwareId = $routeSoftware instanceof \App\Models\Software ? $routeSoftware->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('software', 'name')
                    ->where(fn($q) => $q->where('organization_id', $orgId)
                        ->where('vendor', $this->input('vendor')))
                    ->ignore($softwareId),
            ],
            'vendor' => ['nullable', 'string', 'max:200'],
            'kind' => ['required', new Enum(SoftwareKind::class)],
            'license_type' => ['required', new Enum(SoftwareLicenseType::class)],
            'default_version' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
