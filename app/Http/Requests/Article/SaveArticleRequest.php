<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveArticleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Article;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Enums\Invoicing\PlaceOfSupplyKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Article\Article;
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
        'default_procedure_template_version_id' => \App\Models\Procedure\ProcedureTemplateVersion::class,
        'tag_ids' => \App\Models\Classification\Tag::class,
        'product_id' => \App\Models\Article\Product::class,
        // W3.3: Rabattgruppe kam bisher als rohe ID aus dem Dialog.
        'sales_discount_group_id' => \App\Models\Sales\SalesDiscountGroup::class,
    ];

    /** @var list<string> */
    private const array FLAGS = [
        'stockable', 'purchasable', 'sellable', 'manufacturable',
        'batch_required', 'serial_required', 'shelf_life_required',
    ];

    protected function prepareForValidation(): void {
        if ($this->filled('currency')) {
            $this->merge(['currency' => $this->string('currency')->upper()->value()]);
        }
        // Zolltarifnummern werden oft gegliedert eingetippt („8471 30 00“).
        if ($this->filled('customs_tariff_number')) {
            $this->merge(['customs_tariff_number' => (string) preg_replace('/[\s.]/', '', $this->string('customs_tariff_number')->value())]);
        }
        if ($this->filled('origin_country')) {
            $this->merge(['origin_country' => $this->string('origin_country')->trim()->upper()->value()]);
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

        // SKU-/GTIN-Hoheit (MVP-1583): führt eine Warenwirtschaft die Nummern, schreibt sie nur deren Abgleich.
        $lockedNumbers = Rule::prohibitedIf(fn (): bool => app(\App\Services\Numbering\NumberAuthority::class)->articleNumbersLockedBy($organizationId) !== null);

        return [
            'name' => ['required', 'string', 'max:255'],
            'number' => [
                $lockedNumbers,
                'nullable', 'string', 'max:64',
                Rule::unique('articles', 'number')
                    ->where(fn($q) => $q->where('organization_id', $organizationId))
                    ->ignore($article?->id),
            ],
            'gtin' => [$lockedNumbers, 'nullable', 'string', 'max:14'],
            // Zollangaben (MVP-1007): HS-Code bis TARIC-Zusatz, 6–11 Ziffern.
            'customs_tariff_number' => ['nullable', 'string', 'max:11', 'regex:/^\d{6,11}$/'],
            'origin_country' => ['nullable', Rule::enum(\CommonToolkit\Enums\CountryCode::class)],
            'net_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            // Typ-Zuordnung (produktmodell-konzept.md, MVP-370).
            'product_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('products')],
            'type' => ['required', Rule::enum(ArticleType::class)],
            'base_unit' => ['required', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:64'],
            'assembly_minutes' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'copper_weight' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'copper_base_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'subcategory' => ['nullable', 'string', 'max:64'],
            'sales_discount_group_id' => [
                'nullable', 'integer',
                Rule::exists('sales_discount_groups', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'tax_class' => ['nullable', 'string', 'max:40'],
            // Leistungsort einer Leistung (§ 3a UStG, MVP-1688); leer = Vorgabe der Organisation.
            'place_of_supply_kind' => ['nullable', Rule::enum(PlaceOfSupplyKind::class)],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
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
            // Lager (MVP-1579): leer = Verfahren der Organisation bzw. Nummernkreis der Organisation.
            'valuation_method' => ['sometimes', 'nullable', Rule::enum(\App\Enums\Inventory\ValuationMethod::class)],
            'serial_scheme' => ['sometimes', 'nullable', 'array'],
            'serial_scheme.prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/'],
            'serial_scheme.padding' => ['nullable', 'integer', 'min:1', 'max:12'],
            'serial_scheme.check_digit' => ['nullable', 'boolean'],
            // Artikel-Tagging (MVP-339): analog PartyFormFields.
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('tags')],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Ohne Präfix gibt es kein Artikelschema: der Generator fällt dann auf den
     * Nummernkreis der Organisation zurück, die Spalte bleibt leer.
     */
    public function validated($key = null, $default = null): mixed {
        /** @var array<string, mixed> $data */
        $data = parent::validated();
        if (array_key_exists('serial_scheme', $data)) {
            $scheme = (array) $data['serial_scheme'];
            $prefix = trim((string) ($scheme['prefix'] ?? ''));
            $data['serial_scheme'] = $prefix === '' ? null : [
                'prefix' => $prefix,
                'padding' => (int) ($scheme['padding'] ?? 5),
                'check_digit' => (bool) ($scheme['check_digit'] ?? false),
            ];
        }

        return data_get($data, $key, $default);
    }

    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof \App\Models\Platform\Organization) {
                return (int) $organization->id;
            }
        }

        $user = \Illuminate\Support\Facades\Auth::user();

        return $user?->organization_id !== null ? (int) $user->organization_id : null;
    }
}
