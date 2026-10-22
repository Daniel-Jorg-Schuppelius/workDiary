<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignOpenIssueRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\OpenIssue;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Platform\User;

/**
 * Validierung für die (De-)Zuweisung eines offenen Punkts — leeres Feld
 * hebt die Zuweisung auf. Berechtigung trägt der Controller
 * (OpenIssuePolicy::assign).
 */
class AssignOpenIssueRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> Der Zuweisen-Dialog sendet die Sqid der Person. */
    protected array $sqidFields = ['assignee_user_id' => User::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'assignee_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
        ];
    }
}
