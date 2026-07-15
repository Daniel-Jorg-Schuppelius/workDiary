<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransitionOpenIssueRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\OpenIssue;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für Statusübergänge eines offenen Punkts. Die Regeln hängen
 * von der Aktion (Routenparameter `action`) ab: `block`/`wontDo`/`reopen`
 * verlangen einen Grund, `complete` eine Lösung; `start`/`unblock` (und
 * unbekannte Aktionen, die der Controller ablehnt) haben keine Felder.
 * Berechtigung trägt der Controller (OpenIssuePolicy).
 */
class TransitionOpenIssueRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return match ((string) $this->route('action')) {
            'block', 'wontDo', 'reopen' => ['reason' => ['required', 'string', 'max:2000']],
            'complete' => ['resolution' => ['required', 'string', 'max:5000']],
            default => [],
        };
    }
}
