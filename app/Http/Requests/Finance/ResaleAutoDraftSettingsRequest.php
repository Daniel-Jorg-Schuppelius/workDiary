<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleAutoDraftSettingsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseFormRequest;

/**
 * Serienrechnung (Feature 152): Org-Schalter und Vorlauf in Tagen —
 * Grenzen wie die Registry-Definition (`resale.auto_local_drafts_lead_days`).
 */
class ResaleAutoDraftSettingsRequest extends BaseFormRequest {
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'auto_local_drafts' => ['nullable', 'in:0,1'],
            'lead_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ];
    }

    public function enabled(): bool {
        return $this->boolean('auto_local_drafts');
    }

    public function leadDays(): int {
        return (int) ($this->validated('lead_days') ?? 0);
    }
}
