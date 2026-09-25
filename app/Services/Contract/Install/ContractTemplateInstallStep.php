<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplateInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Contract\Install;

use App\Enums\Contract\ContractKind;
use App\Models\Contract\ContractTemplate;
use App\Models\Platform\{Organization, User};
use App\Services\Classification\Contracts\ProfileInstallStep;

/**
 * Branchenprofil-Abschnitt `contract_templates` (MVP-893): Zeilen
 * `{name, kind, title?, term_kind?, min_term_months?, auto_renew?,
 * renew_period_months?, notice_period_days?, value_period?, obligations?}`.
 * Vorhandene Vorlagen gleichen Namens bleiben unberührt.
 */
final class ContractTemplateInstallStep implements ProfileInstallStep {
    private const FIELDS = ['title', 'term_kind', 'min_term_months', 'auto_renew', 'renew_period_months', 'notice_period_days', 'value_period', 'indexation_method', 'note'];

    public function key(): string {
        return 'contract_templates';
    }

    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            $kind = is_array($row) ? ContractKind::tryFrom((string) ($row['kind'] ?? '')) : null;
            if ($name === '' || $kind === null
                || ContractTemplate::query()->where('organization_id', $organization->id)->where('name', $name)->exists()) {
                $skipped++;

                continue;
            }

            ContractTemplate::query()->create(array_intersect_key($row, array_flip(self::FIELDS)) + [
                'organization_id' => $organization->id,
                'name' => $name,
                'kind' => $kind->value,
                'obligations' => is_array($row['obligations'] ?? null) ? array_values($row['obligations']) : [],
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
