<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateInvestmentStatusRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für den manuellen Statuswechsel einer Investitionsakte.
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class UpdateInvestmentStatusRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Freigabe-/Abschluss-Status laufen NUR über Service-Aktionen.
            'status' => ['required', 'in:idea,screening,comparison,budget_request,in_progress,completed,deferred'],
        ];
    }
}
