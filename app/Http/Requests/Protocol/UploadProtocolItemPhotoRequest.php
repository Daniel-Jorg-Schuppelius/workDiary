<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UploadProtocolItemPhotoRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\ProtocolItemPhotoPhase;
use App\Http\Requests\BaseFormRequest;
use App\Services\Protocol\ProtocolItemPhotoService;
use Illuminate\Validation\Rule;

/**
 * Validierung für den Foto-Upload zu einer Protokollposition (Vorher/
 * Nachher-Phase). Berechtigung trägt der Controller (ProtocolPolicy).
 */
class UploadProtocolItemPhotoRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'max:' . (ProtocolItemPhotoService::MAX_BYTES / 1024),
            ],
            'phase' => [
                'required',
                'string',
                Rule::in(array_column(ProtocolItemPhotoPhase::cases(), 'value')),
            ],
            'caption' => ['nullable', 'string', 'max:180'],
            'allow_geo' => ['nullable', 'boolean'],
        ];
    }
}
