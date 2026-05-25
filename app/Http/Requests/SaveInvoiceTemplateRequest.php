<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveInvoiceTemplateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceTemplateRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $templateId = $this->route('template')?->id;
        $orgId = $this->user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('invoice_templates', 'slug')
                    ->where(fn($q) => $q->where('organization_id', $orgId))
                    ->ignore($templateId),
            ],
            'header_text' => ['nullable', 'string', 'max:2000'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'accent_color' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
