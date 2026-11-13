<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaterialUsageRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Material;

use App\Enums\Article\ArticleStatus;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Article\ArticleVariant;
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Time\Timesheet;
use Illuminate\Validation\Validator;

class SaveMaterialUsageRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'material_id' => \App\Models\Material\Material::class,
        'article_variant_id' => ArticleVariant::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'material_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('materials')],
            // Variante des lagergeführten Artikels (MVP-1690); leer = Standardvariante.
            'article_variant_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('article_variants')],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999.999'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Lagergeführter Artikel ohne ausreichenden Bestand (MVP-1679); das Recht prüft die Lagerbuchung.
            'allow_negative' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Die Variante gehört zum Artikel des Materials (MVP-1690); eine
     * stillgelegte nur, wenn die Zeile sie schon trägt. Zeilen- und
     * Zettelsumme sind decimal(12,2): Menge × Preis (auch der aus Variante
     * oder Materialstamm übernommene) darf sie nicht sprengen (UI-Fuzz
     * 2026-09-21: 1264). Ohne Zettel in der Route (Tagesbuchung) prüft
     * {@see \App\Services\Material\MaterialUsageRecorder} die Summe.
     * Sqid-Felder nur dekodiert aus `getData()`.
     */
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(['material_id', 'article_variant_id'])) {
                return;
            }
            $data = $v->getData();
            $usage = $this->route('usage');
            // Ohne gesendetes Material behält eine Änderung das bisherige.
            $materialId = array_key_exists('material_id', $data) ? $data['material_id'] : ($usage instanceof MaterialUsage ? $usage->material_id : null);
            $material = filled($materialId) ? Material::query()->find((int) $materialId) : null;
            $variantId = $data['article_variant_id'] ?? null;
            $variant = filled($variantId) ? ArticleVariant::query()->find((int) $variantId) : null;
            if (filled($variantId)) {
                $current = $variant instanceof ArticleVariant && $usage instanceof MaterialUsage && (int) $usage->article_variant_id === (int) $variant->id;
                if (! $variant instanceof ArticleVariant || ! $material instanceof Material || $material->article_id === null || (int) $variant->article_id !== (int) $material->article_id) {
                    $v->errors()->add('article_variant_id', __('material.variant.mismatch'));

                    return;
                }
                if ($variant->status !== ArticleStatus::Active && ! $current) {
                    $v->errors()->add('article_variant_id', __('material.variant.inactive'));

                    return;
                }
            }

            $timesheet = $this->route('timesheet');
            if (! $timesheet instanceof Timesheet || $v->errors()->hasAny(['quantity', 'unit_price'])) {
                return;
            }
            $price = $data['unit_price'] ?? null;
            if (! is_numeric($price)) {
                $price = MaterialUsage::defaultUnitPrice($material, $variant);
            }
            if (MaterialUsage::exceedsTimesheetSum($timesheet, $data['quantity'] ?? null, $price, $usage instanceof MaterialUsage ? (int) $usage->getKey() : null)) {
                $v->errors()->add('quantity', __('Menge × Einzelpreis übersteigt die höchste speicherbare Summe des Stundenzettels.'));
            }
        });
    }
}
