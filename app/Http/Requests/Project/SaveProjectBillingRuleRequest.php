<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectBillingRuleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Project;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Project\Project;
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectBillingRuleRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user()?->canManageBilling() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $kinds = TimeEntryKind::values();

        return [
            'applies_to_kind' => ['nullable', 'string', Rule::in($kinds)],
            // Formularschlüssel des Artikelkatalogs (`quelle:sqid`, MVP-1026);
            // der Katalog sucht nur in der Organisation des Projekts.
            'article' => [
                'nullable',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && $value !== '' && $this->catalogArticle() === null) {
                        $fail((string) __('article.catalog.unknown'));
                    }
                },
            ],
            'item_type' => ['nullable', 'string', Rule::in(['service', 'material', 'custom'])],
            'unit_name' => ['nullable', 'string', 'max:50'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'net_unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /** Artikel aus dem Katalog der Projekt-Organisation, sonst null. */
    public function catalogArticle(): ?CatalogArticle {
        $project = $this->route('project');
        $value = $this->input('article');

        return $project instanceof Project && is_string($value) && $value !== ''
            ? app(ArticleCatalog::class)->fromFormKey((int) $project->organization_id, $value)
            : null;
    }

    /** @return array<string, mixed> Regelwerte ohne Formularschlüssel, mit Katalogschlüssel. */
    public function ruleAttributes(): array {
        $data = $this->validated();
        unset($data['article']);
        if ($this->has('article')) {
            $data['article_ref'] = $this->catalogArticle()?->key;
        }

        return $data;
    }
}
