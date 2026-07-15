<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateOpenIssueRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\OpenIssue;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Teil-Aktualisierung eines offenen Punkts (alle Felder
 * `sometimes`). Berechtigung trägt der Controller (OpenIssuePolicy).
 */
class UpdateOpenIssueRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }
}
