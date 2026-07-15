<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCrisisCaseStatusRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für den manuellen Statuswechsel einer Krisenakte.
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class UpdateCrisisCaseStatusRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Aktivierung/Entwarnung/Abschluss laufen NUR über die
            // dedizierten Aktionen (activate/allClear/close).
            'status' => ['required', 'in:assessed,in_progress,stabilized,recovery,discarded'],
        ];
    }
}
