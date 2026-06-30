<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveActivityCategoryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Activity\ActivityCategoryType;
use Illuminate\Validation\Rule;

class SaveActivityCategoryRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'label' => ['required', 'string', 'max:120'],
            'activity_type' => ['required', Rule::enum(ActivityCategoryType::class)],
            'billable_default' => ['nullable', 'boolean'],
            'counts_as_work' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:16'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array {
        $data = parent::validated($key, $default);
        $data['billable_default'] = (bool) ($data['billable_default'] ?? false);
        $data['counts_as_work'] = (bool) ($data['counts_as_work'] ?? true);
        $data['active'] = (bool) ($data['active'] ?? true);

        return $data;
    }
}
