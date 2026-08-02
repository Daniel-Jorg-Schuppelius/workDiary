<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDisposalItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Disposal;

use App\Http\Requests\BaseFormRequest;
use App\Rules\ExistsInCurrentOrganization;
use CommonToolkit\ValueObjects\WasteCode;
use Illuminate\Validation\Validator;

/**
 * Geräteposition (Feature 100): Kategorie, Hersteller/Modell, Seriennummer,
 * Menge/Gewicht, AVV-Schlüssel (WasteCode-VO, Gefährlichkeit wird im Service
 * abgeleitet), optionaler Asset-Bezug.
 */
class SaveDisposalItemRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'category' => ['required', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'condition_note' => ['nullable', 'string', 'max:180'],
            'avv_code' => ['required', 'string', 'max:12'],
            'has_data_storage' => ['nullable', 'boolean'],
            'asset_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('assets')],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $code = (string) $this->input('avv_code', '');
            if ($code !== '' && WasteCode::tryFrom($code) === null) {
                $validator->errors()->add('avv_code', (string) __('Ungültiger AVV-Abfallschlüssel (Format: 20 01 35 oder 20 01 35*).'));
            }
        });
    }
}
