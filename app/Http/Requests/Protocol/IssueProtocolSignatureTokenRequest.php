<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueProtocolSignatureTokenRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\ProtocolSignatureRole;
use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Ausstellen eines externen Signatur-Links.
 * Berechtigung trägt der Controller (sign-Policy + Permission).
 */
class IssueProtocolSignatureTokenRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Rule::enum statt Handliste (Vollaudit 2026-07, N48).
            'role' => ['required', 'string', \Illuminate\Validation\Rule::enum(ProtocolSignatureRole::class)],
            'signer_name' => ['nullable', 'string', 'max:120'],
            'signer_email' => ['required', 'email', 'max:180'],
            'ttl_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
