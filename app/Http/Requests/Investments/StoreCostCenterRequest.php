<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCostCenterRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Anlegen einer Kostenstelle (D2, Blocked-State-
 * Auflösung). Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class StoreCostCenterRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'code' => ['required', 'string', 'max:30'],
            'label' => ['required', 'string', 'max:200'],
        ];
    }
}
