<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveFeeTariffRateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubFeeProration;
use App\Enums\Finance\RecurringInterval;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Tarifsatz je Gültigkeitsdatum (MVP-849); Betrag als Text, Normalisierung im Dienst. */
class SaveFeeTariffRateRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'valid_from' => ['required', 'date'],
            'interval' => ['required', 'string', Rule::enum(RecurringInterval::class)],
            'amount' => ['required', 'string', 'max:20'],
            'anchor_month' => ['required', 'integer', 'min:1', 'max:12'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'proration' => ['required', 'string', Rule::enum(ClubFeeProration::class)],
            'admission_fee' => ['nullable', 'string', 'max:20'],
        ];
    }
}
