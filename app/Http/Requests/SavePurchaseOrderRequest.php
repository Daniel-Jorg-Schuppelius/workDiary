<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePurchaseOrderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;

/**
 * Validierung für die Anlage einer Bestellung (Feature 048, E4). Lieferant und
 * Lager kommen als Sqid; die Berechtigung trägt der Controller.
 */
class SavePurchaseOrderRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'supplier' => \App\Models\Supplier::class,
        'warehouse' => \App\Models\Warehouse::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'supplier' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('suppliers')],
            'warehouse' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('warehouses')],
            'expected_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
