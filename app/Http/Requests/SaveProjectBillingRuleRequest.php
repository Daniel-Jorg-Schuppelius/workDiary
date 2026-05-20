<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectBillingRuleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\LexofficeArticle;
use App\Models\TimeEntry;
use App\Enums\TimeEntry\TimeEntryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectBillingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageBilling() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kinds = TimeEntryKind::values();

        return [
            'plugin_id' => ['nullable', 'string', 'max:50'],
            'applies_to_kind' => ['nullable', 'string', Rule::in($kinds)],
            'lexoffice_article_id' => [
                'nullable',
                'string',
                Rule::exists((new LexofficeArticle)->getTable(), 'external_id'),
            ],
            'item_type' => ['nullable', 'string', Rule::in(['service', 'material', 'custom'])],
            'unit_name' => ['nullable', 'string', 'max:50'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'net_unit_price' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
