<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDataMediaTreatmentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Disposal;

use App\Enums\Disposal\{DataMediumType, DinCategory, MediaTreatmentMethod};
use App\Http\Requests\BaseFormRequest;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Datenträger-Behandlung (Feature 100): Verfahren, DIN-66399-Kategorie +
 * Sicherheitsstufe (1–7), Schutzklasse (1–3), Zeitpunkt, Durchführender,
 * Beleg-Referenz.
 */
class SaveDataMediaTreatmentRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'media_type' => ['required', Rule::enum(DataMediumType::class)],
            'method' => ['required', Rule::enum(MediaTreatmentMethod::class)],
            'din_category' => ['required', Rule::enum(DinCategory::class)],
            'security_level' => ['required', 'integer', 'min:1', 'max:7'],
            'protection_class' => ['nullable', 'integer', 'min:1', 'max:3'],
            'treated_at' => ['required', 'date'],
            'performed_by_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'evidence_reference' => ['nullable', 'string', 'max:180'],
        ];
    }
}
