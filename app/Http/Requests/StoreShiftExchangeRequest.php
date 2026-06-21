<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreShiftExchangeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{ScheduledShift, ShiftExchange, User};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{Auth, Gate};

class StoreShiftExchangeRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'scheduled_shift_id' => ScheduledShift::class,
        'target_user_id' => User::class,
        'offered_shift_id' => ScheduledShift::class,
    ];

    public function authorize(): bool {
        return Auth::user() instanceof User && Gate::allows('create', ShiftExchange::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'scheduled_shift_id' => ['required', 'integer', 'exists:scheduled_shifts,id'],
            'target_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'offered_shift_id' => ['nullable', 'integer', 'exists:scheduled_shifts,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
