<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacyDutyRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Legacy\Models\LegacyUser;
use App\Support\Sqid;
use Illuminate\Foundation\Http\FormRequest;

class SaveLegacyDutyRequest extends FormRequest {
    protected function prepareForValidation(): void {
        $rawUser = $this->input('user');
        $userId = Sqid::decode(LegacyUser::class, $rawUser);
        if ($userId === null && is_numeric($rawUser)) {
            $userId = (int) $rawUser;
        }

        $this->merge(['user' => $userId]);
    }

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user' => ['required', 'integer', 'min:4', 'exists:legacy.user,id'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ];
    }
}
