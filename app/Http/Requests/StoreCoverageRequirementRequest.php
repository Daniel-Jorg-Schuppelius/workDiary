<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCoverageRequirementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreCoverageRequirementRequest extends FormRequest {
    use DecodesSqidInputs {
        validationData as decodeSqidValidationData;
    }

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'shift_type_id' => \App\Models\ShiftType::class,
        // Checkboxen posten Sqids — elementweise dekodieren.
        'required_qualification_ids' => \App\Models\Qualification::class,
    ];

    public function authorize(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * MVP-530: `qualification_minima` kommt als Map Sqid → Anzahl aus dem
     * Formular; Keys hier auf numerische IDs umschlüsseln (der Trait
     * dekodiert nur Werte). Leere/0-Einträge fallen weg.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array {
        // Erst die Trait-Dekodierung der $sqidFields, dann die Minima-Keys.
        $data = $this->decodeSqidValidationData();

        $minima = $data['qualification_minima'] ?? null;
        if (is_array($minima)) {
            $mapped = [];
            foreach ($minima as $key => $count) {
                if (! is_numeric($count) || (int) $count < 1) {
                    continue;
                }
                $id = is_string($key)
                    ? \App\Support\Sqid::decodeOrNumeric(\App\Models\Qualification::class, $key)
                    : (int) $key;
                if ($id !== null && (int) $id > 0) {
                    $mapped[(int) $id] = (int) $count;
                }
            }
            $data['qualification_minima'] = $mapped === [] ? null : $mapped;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'shift_type_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('shift_types')],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'specific_date' => ['nullable', 'date'],
            'min_staff' => ['required', 'integer', 'min:0', 'max:99'],
            'max_staff' => ['nullable', 'integer', 'min:0', 'max:99', 'gte:min_staff'],
            // Ideal-Besetzung zwischen Min und Max (Q1-Kennlinien).
            'ideal_staff' => ['nullable', 'integer', 'min:0', 'max:99', 'gte:min_staff'],
            'required_qualification_ids' => ['nullable', 'array'],
            'required_qualification_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('qualifications')],
            // MVP-530: Mindestanzahl je Qualifikation (Keys in validationData() dekodiert).
            'qualification_minima' => ['nullable', 'array'],
            'qualification_minima.*' => ['integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            /** @var array<int, int> $minima */
            $minima = (array) ($v->getData()['qualification_minima'] ?? []);
            if ($minima === []) {
                return;
            }
            $ids = array_map('intval', array_keys($minima));
            $known = \App\Models\Qualification::query()->whereIn('id', $ids)->count();
            if ($known !== count($ids)) {
                $v->errors()->add('qualification_minima', __('Unbekannte Qualifikation in der Mindestbesetzung.'));
            }
        });
    }
}
