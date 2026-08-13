<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreShiftTypeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreShiftTypeRequest extends FormRequest {
    public function authorize(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    protected function prepareForValidation(): void {
        // Farbwert auf #rrggbb normalisieren (Vollaudit 2026-07, N49) — die
        // gemeinsame Rule akzeptiert die Raute optional, gespeichert wird
        // weiterhin einheitlich MIT Raute (Views nutzen den Wert direkt).
        if (is_string($this->input('color'))) {
            $normalized = \CommonToolkit\Helper\Data\ColorHelper::normalizeHex((string) $this->input('color'));
            if ($normalized !== null) {
                $this->merge(['color' => $normalized]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return $this->fieldRules(false);
    }

    /**
     * Gemeinsame Feldregeln für Anlegen/Bearbeiten. $partial=true (PATCH)
     * macht Pflichtfelder optional (sometimes statt required).
     *
     * @return array<string, mixed>
     */
    protected function fieldRules(bool $partial): array {
        $req = $partial ? ['sometimes'] : ['required'];
        $opt = $partial ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'name' => [...$req, 'string', 'max:100'],
            'abbreviation' => [...$req, 'string', 'max:5'],
            // Gemeinsame Farb-Rule (Vollaudit 2026-07, N49); prepareForValidation normalisiert auf #rrggbb.
            'color' => [...$req, 'string', new \App\Rules\HexColor()],
            'default_start_time' => [...$opt, 'date_format:H:i'],
            'default_end_time' => [...$opt, 'date_format:H:i'],
            // Kombi-Dienst (Feature-103-Delta): anschließende Rufbereitschaft.
            'on_call_start_time' => [...$opt, 'date_format:H:i'],
            'on_call_end_time' => [...$opt, 'date_format:H:i', 'required_with:on_call_start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
