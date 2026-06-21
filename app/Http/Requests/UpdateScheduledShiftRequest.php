<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateScheduledShiftRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\ScheduledShift;
use Illuminate\Validation\Validator;

class UpdateScheduledShiftRequest extends StoreScheduledShiftRequest {
    /**
     * Wie beim Anlegen, aber als Teil-Update (PATCH): Pflichtfelder werden
     * optional (sometimes).
     *
     * @return array<string, mixed>
     */
    public function rules(): array {
        return $this->fieldRules(true);
    }

    public function withValidator(Validator $validator): void {
        /** @var ScheduledShift|null $shift */
        $shift = $this->route('shift');
        $this->attachComplianceCheck($validator, $shift instanceof ScheduledShift ? $shift : null);
    }
}
