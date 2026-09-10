<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WaiveResalePeriodRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Enums\Reselling\PeriodStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Periode bewusst nicht berechnen oder als strittig markieren — mit Grund (Feature 152). */
class WaiveResalePeriodRequest extends BaseFormRequest {
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'decision' => ['required', Rule::in(['waived', 'disputed'])],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function status(): PeriodStatus {
        return $this->validated('decision') === 'waived' ? PeriodStatus::Waived : PeriodStatus::Disputed;
    }

    public function reason(): string {
        return (string) $this->validated('reason');
    }
}
