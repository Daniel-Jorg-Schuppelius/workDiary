<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAttendanceSheetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubAttendanceStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Sammelerfassung der Anwesenheitsliste (MVP-844): Zeilen je Mitglied
 * (Sqid als Schlüssel, Dekodierung im Controller), Sperrzähler, durchgeführte Dauer.
 */
class SaveAttendanceSheetRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'conducted_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'records' => ['nullable', 'array', 'max:500'],
            'records.*.status' => ['nullable', 'string', Rule::enum(ClubAttendanceStatus::class)],
            'records.*.minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'records.*.arrived_at' => ['nullable', 'date_format:H:i'],
            'records.*.left_at' => ['nullable', 'date_format:H:i'],
        ];
    }
}
