<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveManufacturingOrderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;

/**
 * Validierung für die Anlage eines Fertigungsauftrags (Feature 047, MVP-062).
 * Artikel/Variante/Lager kommen als Sqid; Berechtigung trägt der Controller.
 */
class SaveManufacturingOrderRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'article' => \App\Models\Article::class,
        'variant' => \App\Models\ArticleVariant::class,
        'warehouse' => \App\Models\Warehouse::class,
        'customer' => \App\Models\Customer::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'article' => ['required', 'integer', 'exists:articles,id'],
            'variant' => ['nullable', 'integer', 'exists:article_variants,id'],
            'warehouse' => ['nullable', 'integer', 'exists:warehouses,id'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'target_qty' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:20'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
