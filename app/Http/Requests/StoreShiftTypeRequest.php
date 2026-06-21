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
            'color' => [...$req, 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_start_time' => [...$opt, 'date_format:H:i'],
            'default_end_time' => [...$opt, 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
