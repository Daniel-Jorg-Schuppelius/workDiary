<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CorrectAttendanceRecordRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubAttendanceStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Einzelkorrektur eines Nachweises (MVP-844); Grund nach der ersten Bestätigung Pflicht (Service). */
class CorrectAttendanceRecordRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::enum(ClubAttendanceStatus::class)],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'arrived_at' => ['nullable', 'date_format:H:i'],
            'left_at' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
