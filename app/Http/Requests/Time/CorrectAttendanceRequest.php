<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CorrectAttendanceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Time;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\ParsesOrgLocalDateTimes;
use App\Services\TimeApproval\TimeCorrectionService;

/**
 * Eigene Stempelung korrigieren (MVP-1595): Dialog auf „Heute“ und
 * Offline-Befehl `attendance.correct`. `base_version` ist der Stand, den das
 * Formular gezeigt hat ({@see \App\Models\Time\Attendance::correctionVersion()}).
 */
class CorrectAttendanceRequest extends BaseFormRequest {
    use ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        foreach (['started_at', 'ended_at', 'break_minutes'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
        // Wanduhrzeit des Formulars in der Anzeige-Zeitzone; Zeitstempel mit Versatz bleiben, wie sie sind.
        $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'base_version' => ['required', 'string', 'max:64'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => $this->filled('started_at') ? ['nullable', 'date', 'after:started_at'] : ['nullable', 'date'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'reason' => ['required', 'string', 'min:' . TimeCorrectionService::REASON_MIN_LENGTH, 'max:4000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'ended_at.after' => __('„Bis" muss nach „Von" liegen.'),
        ];
    }
}
