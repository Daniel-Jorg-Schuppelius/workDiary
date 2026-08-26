<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveConstructionNoticeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;

/**
 * VOB/B-Schreiben anlegen/bearbeiten (Feature 062, MVP-728). Bezuege kommen als
 * Sqid und werden org-gescopt geprueft; die Belegart steht in der Route, nicht
 * im Formular — sie entscheidet ueber Rechtsverweis und Vorlage.
 */
class SaveConstructionNoticeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'project_id' => \App\Models\Project::class,
        'site_id' => \App\Models\Site::class,
        'customer_id' => \App\Models\Customer::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'occurred_on' => ['required', 'date'],
            'facts' => ['required', 'string', 'min:10', 'max:20000'],
            'impact_schedule' => ['nullable', 'string', 'max:5000'],
            'impact_cost' => ['nullable', 'string', 'max:5000'],
            'claims_time_extension' => ['nullable', 'boolean'],
            'legal_reference' => ['nullable', 'string', 'max:120'],
            'recipient_name' => ['nullable', 'string', 'max:200'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:190'],
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'site_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('sites')],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
        ];
    }
}
