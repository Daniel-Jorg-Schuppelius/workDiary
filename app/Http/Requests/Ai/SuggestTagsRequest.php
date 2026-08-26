<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuggestTagsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use App\Enums\Classification\ClassificationDomain;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Customer;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * KI-Tag-/Katalogvorschlag aus Freitext (Feature 143, MVP-711) — JSON-
 * Endpunkt `ai.suggest.tags`. Kunde als Sqid (nur eigener Mandant), Domäne
 * optional für zusätzliche Katalogwerte.
 */
class SuggestTagsRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'text' => ['required', 'string', 'max:5000'],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'domain' => ['nullable', 'string', Rule::enum(ClassificationDomain::class)],
        ];
    }
}
