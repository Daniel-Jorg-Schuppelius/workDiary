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

use App\Enums\Investments\InvestmentCaseStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für den manuellen Statuswechsel einer Investitionsakte.
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class UpdateInvestmentStatusRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Freigabe-/Abschluss-Status laufen NUR über Service-Aktionen.
            'status' => ['required', Rule::enum(InvestmentCaseStatus::class)->only(InvestmentCaseStatus::manual())],
        ];
    }
}
