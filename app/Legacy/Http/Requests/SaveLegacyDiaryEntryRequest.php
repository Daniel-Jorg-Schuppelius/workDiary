<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLegacyDiaryEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Requests;

use App\Legacy\Models\LegacyUser;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Foundation\Http\FormRequest;

class SaveLegacyDiaryEntryRequest extends FormRequest {
    /** Werteformat der Datums- und Zeitfelder der klassischen Ansicht (Datumswähler des Browsers). */
    public const string CLASSIC_DATE = 'Y-m-d';

    public const string CLASSIC_TIME = 'H:i';

    protected function prepareForValidation(): void {
        $rawUser = $this->input('user');
        $userId = Sqid::decode(LegacyUser::class, $rawUser);
        if ($userId === null && is_numeric($rawUser)) {
            $userId = (int) $rawUser;
        }

        $this->merge(['user' => $userId]);

        // Klassische Ansicht: Datum und Uhrzeit getrennt; Ungültiges bleibt roh, damit die Regel es meldet.
        foreach (['von', 'bis'] as $field) {
            if ($this->has($field . '_date')) {
                $value = trim(trim((string) $this->input($field . '_date', '')) . ' ' . trim((string) $this->input($field . '_time', '')));
                $this->merge([$field => $value === '' ? null : (DateHelper::normalizeToIso($value) ?? $value)]);
            }
        }
    }

    private function isClassic(): bool {
        return $this->has('von_date');
    }

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $dateRule = $this->isClassic() ? 'date_format:Y-m-d H:i:s' : 'date';

        return [
            'inhalt' => ['required', 'string', 'max:65535'],
            'antwort' => ['nullable', 'string', 'max:65535'],
            'gelesen' => ['required', 'integer', 'in:-1,1,2,3'],
            'von' => ['nullable', $dateRule],
            'bis' => ['nullable', $dateRule, 'after_or_equal:von'],
            'sms' => ['nullable', 'in:j'],
            'user' => ['nullable', 'integer', 'min:4', 'exists:legacy.user,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        // Die klassische Eingabe ist TT.MM.JJJJ; das interne Prüfformat gehört nicht in die Meldung.
        return $this->isClassic()
            ? ['von.date_format' => __('validation.date'), 'bis.date_format' => __('validation.date')]
            : [];
    }
}
