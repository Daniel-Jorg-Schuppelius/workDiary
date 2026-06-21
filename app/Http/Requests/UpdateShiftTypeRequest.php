<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateShiftTypeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

class UpdateShiftTypeRequest extends StoreShiftTypeRequest {
    /**
     * Wie beim Anlegen, aber als Teil-Update (PATCH): Pflichtfelder werden
     * optional (sometimes).
     *
     * @return array<string, mixed>
     */
    public function rules(): array {
        return $this->fieldRules(true);
    }
}
