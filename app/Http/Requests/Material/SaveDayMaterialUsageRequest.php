<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDayMaterialUsageRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Material;

/**
 * Material auf „Heute“ und offline (MVP-1594): dieselben Felder wie am
 * Stundenzettel, dazu Projekt und Tag, über die der offene Zettel gefunden wird.
 */
class SaveDayMaterialUsageRequest extends SaveMaterialUsageRequest {
    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'material_id' => \App\Models\Material\Material::class,
        'project_id' => \App\Models\Project\Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'project_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'date' => ['required', 'date_format:Y-m-d'],
        ] + parent::rules();
    }
}
