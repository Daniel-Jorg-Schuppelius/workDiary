<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveFeeExemptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubFeeExemptionKind;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Befreiung/Ermäßigung (MVP-849) mit Zeitraum und Grund. */
class SaveFeeExemptionRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'kind' => ['required', 'string', Rule::enum(ClubFeeExemptionKind::class)],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
