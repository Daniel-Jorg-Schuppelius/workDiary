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

class SaveTimesheetRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        // status NICHT hier: Anlegen erzwingt Draft, Statusübergänge laufen
        // ausschließlich über submit/sign/lock (Policy + SignatureService).
        // Sonst könnte ein Owner per Massenzuweisung status=signed/locked
        // setzen und Signatur-/Lock-Freigabe umgehen.
        return [
            'work_date' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
