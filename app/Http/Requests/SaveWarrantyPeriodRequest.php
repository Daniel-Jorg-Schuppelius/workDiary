<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveWarrantyPeriodRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Warranty\{WarrantyBasis, WarrantySide};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Gewährleistungsfrist anlegen (Feature 115, MVP-604). Bezüge kommen als
 * Sqid und werden org-gescopt geprüft; die Fristlogik (Grundlage → Enddatum,
 * Begründungspflicht bei Abweichung) liegt im WarrantyService.
 */
class SaveWarrantyPeriodRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'protocol_id' => \App\Models\Protocol::class,
        'project_id' => \App\Models\Project::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'customer_id' => \App\Models\Customer::class,
        'supplier_id' => \App\Models\Supplier::class,
        'responsible_user_id' => \App\Models\User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'side' => ['required', Rule::enum(WarrantySide::class)],
            'basis' => ['required', Rule::enum(WarrantyBasis::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'protocol_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('protocols')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'trade' => ['nullable', 'string', 'max:120'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
