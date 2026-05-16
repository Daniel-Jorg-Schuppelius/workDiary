<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacyUserRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Legacy\Models\LegacyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLegacyUserRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var LegacyUser|null $legacyUser */
        $legacyUser = $this->route('user');

        return [
            'uname' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/', Rule::unique('legacy.user', 'uname')->ignore($legacyUser?->id)],
            'userpw' => $this->isMethod('PUT')
                ? ['nullable', 'string', 'max:15', 'not_regex:/[\x00-\x1F\x7F]/']
                : ['required', 'string', 'max:15', 'not_regex:/[\x00-\x1F\x7F]/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
