<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveEntryTypeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\DiaryEntry;
use App\Models\EntryType;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy greift im Controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'requires_customer' => $this->boolean('requires_customer'),
            'requires_address' => $this->boolean('requires_address'),
            'requires_schedule' => $this->boolean('requires_schedule'),
            'requires_tour' => $this->boolean('requires_tour'),
            'allow_priority' => $this->boolean('allow_priority'),
            'allow_tour' => $this->boolean('allow_tour') || $this->boolean('requires_tour'),
        ]);

        if ($this->input('default_priority') === '') {
            $this->merge(['default_priority' => null]);
        }
        if ($this->input('default_service_minutes') === '') {
            $this->merge(['default_service_minutes' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var EntryType|null $entryType */
        $entryType = $this->route('entryType');
        $orgId = $entryType?->organization_id;
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
                Rule::unique('entry_types', 'slug')
                    ->where(fn ($q) => $q->where('organization_id', $orgId))
                    ->ignore($entryType?->id),
            ],
            'label' => ['required', 'string', 'max:120'],
            'icon' => ['required', 'string', 'max:64'],
            'color' => ['required', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'requires_customer' => ['boolean'],
            'requires_address' => ['boolean'],
            'requires_schedule' => ['boolean'],
            'requires_tour' => ['boolean'],
            'allow_priority' => ['boolean'],
            'allow_tour' => ['boolean'],
            'default_status' => ['required', 'integer', 'in:-1,1,2,3'],
            'default_service_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'default_priority' => ['nullable', Rule::in(DiaryEntry::PRIORITIES)],
        ];
    }
}
