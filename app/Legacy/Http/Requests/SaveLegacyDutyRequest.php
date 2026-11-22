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

        // Klassische Ansicht: Datum in Tag, Monat und Jahr getrennt wie im Altportal.
        foreach (['von', 'bis'] as $field) {
            if ($this->has($field . '_day')) {
                $parts = array_map(fn(string $part) => trim((string) $this->input($field . '_' . $part, '')), ['year', 'month', 'day']);
                $this->merge([$field => implode('', $parts) === '' ? null : sprintf('%s-%s-%s', $parts[0], str_pad($parts[1], 2, '0', STR_PAD_LEFT), str_pad($parts[2], 2, '0', STR_PAD_LEFT))]);
            }
        }
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
