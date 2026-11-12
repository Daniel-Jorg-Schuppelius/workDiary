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
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Asset\Asset;
use App\Models\Disposal\DisposalItem;
use App\Rules\ExistsInCurrentOrganization;
use CommonToolkit\ValueObjects\WasteCode;
use Illuminate\Validation\Validator;

/**
 * Geräteposition (Feature 100): Kategorie, Hersteller/Modell, Seriennummer,
 * Menge/Gewicht, AVV-Schlüssel (WasteCode-VO, Gefährlichkeit wird im Service
 * abgeleitet), optionaler Asset-Bezug (nur Assets in Betrieb, MVP-1578).
 */
class SaveDisposalItemRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'asset_id' => Asset::class,
    ];

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

    /**
     * Ein Asset wird einmal entsorgt: es muss in Betrieb sein und darf in
     * keiner anderen offenen Akte stehen. Die eigene Position behält ihr Asset.
     */
    private function checkAsset(Validator $validator): void {
        $assetId = $validator->getData()['asset_id'] ?? null;
        $item = $this->route('disposalItem');
        $itemId = $item instanceof DisposalItem ? $item->getKey() : null;
        if ($assetId === null || $assetId === '' || $validator->errors()->has('asset_id')
            || ($item instanceof DisposalItem && (int) $item->asset_id === (int) $assetId)) {
            return;
        }
        if (! Asset::query()->inOperation()->whereKey((int) $assetId)->exists()) {
            $validator->errors()->add('asset_id', (string) __('disposal.item.error.asset_not_in_operation'));

            return;
        }
        $taken = DisposalItem::query()
            ->where('asset_id', (int) $assetId)
            ->when($itemId !== null, fn ($q) => $q->whereKeyNot($itemId))
            ->whereHas('job', fn ($q) => $q->open())
            ->exists();
        if ($taken) {
            $validator->errors()->add('asset_id', (string) __('disposal.item.error.asset_taken'));
        }
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $code = (string) $this->input('avv_code', '');
            if ($code !== '' && WasteCode::tryFrom($code) === null) {
                $validator->errors()->add('avv_code', (string) __('Ungültiger AVV-Abfallschlüssel (Format: 20 01 35 oder 20 01 35*).'));
            }
            $this->checkAsset($validator);
        });
    }
}
