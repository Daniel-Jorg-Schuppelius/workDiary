<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTimesheetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class SaveTimesheetRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        // status NICHT hier: Anlegen erzwingt Draft, Übergänge nur über submit/sign/lock (Policy + SignatureService).
        // Sonst könnte ein Owner per Massenzuweisung status=signed/locked setzen und die Freigabe umgehen.
        return [
            'work_date' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Anlegen führt TimesheetResolver auf den offenen Zettel des Tages zusammen;
     * beim Umdatieren gibt es das nicht — timesheets_open_day_unique warf 500 (UI-Fuzz 2026-09-21).
     */
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            $timesheet = $this->route('timesheet');
            if (! $timesheet instanceof Timesheet || $timesheet->isSigned() || $v->errors()->has('work_date')) {
                return;
            }

            $taken = Timesheet::query()
                ->unsigned()
                ->whereKeyNot($timesheet->getKey())
                ->where('project_id', $timesheet->project_id)
                ->where('user_id', $timesheet->user_id)
                ->where('work_date', CarbonImmutable::parse((string) $this->input('work_date'))->startOfDay())
                ->exists();

            if ($taken) {
                $v->errors()->add('work_date', __('Für diesen Tag gibt es bereits einen offenen Stundenzettel.'));
            }
        });
    }
}
