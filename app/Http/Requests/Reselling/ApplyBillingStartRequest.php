<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplyBillingStartRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Http\Requests\BaseFormRequest;
use App\Services\Reselling\Register\ProviderBillingStart;
use Illuminate\Validation\Rule;

/** Lizenzjahre vor dem Beginn des Portalkontos: verzichten oder noch selbst berechnen (MVP-1747). */
class ApplyBillingStartRequest extends BaseFormRequest {
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'decision' => ['required', Rule::in(ProviderBillingStart::DECISIONS)],
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }
}
