<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveFeeSurchargeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Finance\RecurringInterval;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubDepartment;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Abteilungszuschlag (MVP-849). */
class SaveFeeSurchargeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_department_id' => ClubDepartment::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_department_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_departments')],
            'name' => ['required', 'string', 'max:120'],
            'interval' => ['required', 'string', Rule::enum(RecurringInterval::class)],
            'amount' => ['required', 'string', 'max:20'],
            'anchor_month' => ['required', 'integer', 'min:1', 'max:12'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
