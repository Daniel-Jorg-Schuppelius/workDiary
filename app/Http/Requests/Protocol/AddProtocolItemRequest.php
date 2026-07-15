<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddProtocolItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\ProtocolItemType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für das Hinzufügen einer Protokollposition.
 * Berechtigung trägt der Controller (ProtocolPolicy).
 */
class AddProtocolItemRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'label' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'required' => ['nullable', 'boolean'],
            'item_type' => ['nullable', 'string', 'max:40', Rule::in(array_map(static fn($c) => $c->value, ProtocolItemType::cases()))],
            // Org-Bindung läuft über das Eltern-Protokoll (Items tragen
            // keine eigene organization_id).
            'parent_item_id' => [
                'nullable',
                'integer',
                Rule::exists('protocol_items', 'id')->where(function ($q): void {
                    $orgId = app()->bound('currentOrganization') ? (app('currentOrganization')->id ?? null) : null;
                    if ($orgId !== null) {
                        $q->whereIn('protocol_id', \Illuminate\Support\Facades\DB::table('protocols')->where('organization_id', $orgId)->select('id'));
                    }
                }),
            ],
        ];
    }
}
