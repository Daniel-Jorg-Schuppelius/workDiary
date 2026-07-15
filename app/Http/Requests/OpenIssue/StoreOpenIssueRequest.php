<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreOpenIssueRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\OpenIssue;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueVisibility};
use App\Http\Controllers\OpenIssueController;
use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Anlegen eines offenen Punkts. `subject_id` bleibt
 * bewusst ein String (Sqid ODER rohe ID) — die Auflösung auf das Subjekt
 * (Typ-Whitelist {@see OpenIssueController::SUBJECT_MAP}) übernimmt der
 * Controller. Zusatz-Gates (publishToCustomer/assign) trägt ebenfalls der
 * Controller (OpenIssuePolicy).
 */
class StoreOpenIssueRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'subject_kind' => ['required', 'string', 'in:' . implode(',', array_keys(OpenIssueController::SUBJECT_MAP))],
            'subject_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:40'],
            'severity' => ['nullable', 'string', 'in:' . implode(',', array_column(OpenIssueSeverity::cases(), 'value'))],
            'assignee_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'due_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:' . implode(',', array_column(OpenIssueVisibility::cases(), 'value'))],
        ];
    }
}
