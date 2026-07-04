<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveIdeaMapRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;

/**
 * Validierung für Anlegen/Umbenennen einer Ideenlandkarte (Feature 054,
 * MVP-104/109). Kontextbezüge (Kunde/Projekt) kommen als Sqid und werden
 * mandantensicher geprüft; Berechtigung trägt der Controller (IdeaMapPolicy).
 */
class SaveIdeaMapRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer' => \App\Models\Customer::class,
        'project' => \App\Models\Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'customer' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'project' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
        ];
    }
}
