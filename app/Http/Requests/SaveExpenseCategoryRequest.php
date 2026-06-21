<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveExpenseCategoryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\{ExpenseCategory, Organization};
use Illuminate\Validation\Rule;

class SaveExpenseCategoryRequest extends BaseFormRequest {

    protected function prepareForValidation(): void {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'default_billable' => $this->boolean('default_billable'),
            'requires_receipt' => $this->boolean('requires_receipt'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var ExpenseCategory|null $category */
        $category = $this->route('expenseCategory');
        $orgId = $category?->organization_id;
        if ($orgId === null && app()->bound('currentOrganization')) {
            /** @var Organization|null $org */
            $org = app('currentOrganization');
            $orgId = $org?->id;
        }

        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('expense_categories', 'slug')
                    ->where(fn($q) => $q->where('organization_id', $orgId))
                    ->ignore($category?->id),
            ],
            'label' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['required', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'default_billable' => ['boolean'],
            'requires_receipt' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
