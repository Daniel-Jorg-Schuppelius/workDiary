<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProductRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\Product\ProductStatus;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Classification, Product};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Anlegen/Bearbeiten eines Produkts (MVP-370). Unique je Organisation über
 * (manufacturer, model); die Produktgruppen-Klassifikation ist optional und
 * org-gescopt (inkl. Plattform-Defaults). Berechtigung trägt der Controller
 * (ProductPolicy).
 */
class SaveProductRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'product_group_classification_id' => Classification::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Product|null $product */
        $product = $this->route('product');
        $organizationId = app()->bound('currentOrganization') ? (app('currentOrganization')->id ?? null) : null;

        $unique = Rule::unique('products', 'model')
            ->where('organization_id', $organizationId)
            ->where('manufacturer', trim((string) $this->input('manufacturer')));
        if ($product !== null) {
            $unique = $unique->ignore($product->id);
        }

        return [
            'manufacturer' => ['required', 'string', 'max:190'],
            'model' => ['required', 'string', 'max:190', $unique],
            'name' => ['nullable', 'string', 'max:190'],
            'product_group_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
