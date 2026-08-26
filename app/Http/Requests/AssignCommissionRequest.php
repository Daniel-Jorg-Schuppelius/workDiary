<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignCommissionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;

/**
 * Manuelle Zuordnung Beleg → Vertriebsperson (Feature 146, MVP-729). Ein
 * leeres Feld loest die Zuordnung wieder — dann greift wieder die Herkunft aus
 * der Lead-Pipeline.
 */
class AssignCommissionRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
        ];
    }
}
