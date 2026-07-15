<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreInvestmentReviewRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Nachbewertung einer Investition (MVP-207).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class StoreInvestmentReviewRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'benefit_result' => ['required', 'string', 'max:5000'],
            'economics_result' => ['nullable', 'string', 'max:5000'],
            'lessons' => ['nullable', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
