<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveArticleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Article;
use Illuminate\Validation\Rule;

/**
 * Validierung für Anlage/Bearbeitung eines Artikels (Feature 048, MVP-060).
 * Die SKU (number) wird i. d. R. automatisch vergeben; ein manuell gesetzter
 * Wert (z. B. Übernahme einer führenden externen Nummer) ist je Organisation
 * eindeutig. Berechtigung trägt der Controller (ArticlePolicy).
 */
class SaveArticleRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'default_procedure_template_version_id' => \App\Models\ProcedureTemplateVersion::class,
        'tag_ids' => \App\Models\Tag::class,
        'product_id' => \App\Models\Product::class,
    ];

    /** @var list<string> */
    private const FLAGS = [
        'stockable', 'purchasable', 'sellable', 'manufacturable',
        'batch_required', 'serial_required', 'shelf_life_required',
    ];

    protected function prepareForValidation(): void {
        if ($this->filled('currency')) {
            $this->merge(['currency' => $this->string('currency')->upper()->value()]);
        }
        $merge = [];
        foreach (self::FLAGS as $flag) {
            $merge[$flag] = $this->boolean($flag);
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Article|null $article */
        $article = $this->route('article');
        $organizationId = $article instanceof Article
            ? $article->organization_id
            : $this->currentOrganizationId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'number' => [
                'nullable', 'string', 'max:64',
                Rule::unique('articles', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($article?->id),
            ],
            'gtin' => ['nullable', 'string', 'max:14'],
            // Typ-Zuordnung (produktmodell-konzept.md, MVP-370).
            'product_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('products')],
            'type' => ['required', Rule::enum(ArticleType::class)],
            'base_unit' => ['required', 'string', 'max:20'],
            'tax_class' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', \Illuminate\Validation\Rule::enum(\CommonToolkit\Enums\CurrencyCode::class)],
            // Org-Bindung läuft über die Eltern-Vorlage (Versions-Tabelle
            // selbst trägt keine organization_id).
            'default_procedure_template_version_id' => [
                'nullable',
                'integer',
                Rule::exists('procedure_template_versions', 'id')->where(function ($q): void {
                    $orgId = app()->bound('currentOrganization') ? (app('currentOrganization')->id ?? null) : null;
                    if ($orgId !== null) {
                        $q->whereIn('procedure_template_id', \Illuminate\Support\Facades\DB::table('procedure_templates')->where('organization_id', $orgId)->select('id'));
                    }
                }),
            ],
            ...array_fill_keys(self::FLAGS, ['boolean']),
            // Artikel-Tagging (MVP-339): analog PartyFormFields.
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('tags')],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof \App\Models\Organization) {
                return (int) $organization->id;
            }
        }

        $user = \Illuminate\Support\Facades\Auth::user();

        return $user?->organization_id !== null ? (int) $user->organization_id : null;
    }
}
