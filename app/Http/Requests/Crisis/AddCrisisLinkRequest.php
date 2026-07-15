<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddCrisisLinkRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Verknüpfung eines Fachvorgangs mit der Krisenakte
 * (MVP-218). Die Auflösung der Sqid auf das Zielobjekt (Typ-Whitelist)
 * bleibt im Controller. Berechtigung trägt der Controller.
 */
class AddCrisisLinkRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'linkable_type' => ['required', 'in:service_ticket,isms_incident,privacy_incident,safety_event,procedure_run,document'],
            'linkable_sqid' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
