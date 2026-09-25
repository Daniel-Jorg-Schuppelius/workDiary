<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplateInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol\Install;

use App\Enums\Protocol\ProtocolType;
use App\Models\Platform\{Organization, User};
use App\Models\Protocol\ProtocolTemplate;
use App\Services\Classification\Contracts\ProfileInstallStep;

/**
 * Branchenprofil-Abschnitt `protocol_templates` (MVP-901): Zeilen
 * `{name, kind, description?, items}` im Vorlagenformat. Vorhandene
 * Vorlagen gleichen Namens bleiben unberührt.
 */
final class ProtocolTemplateInstallStep implements ProfileInstallStep {
    public function key(): string {
        return 'protocol_templates';
    }

    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            $kind = is_array($row) ? ProtocolType::tryFrom((string) ($row['kind'] ?? '')) : null;
            $items = is_array($row) && is_array($row['items'] ?? null) ? array_values($row['items']) : [];
            if ($name === '' || $kind === null || $items === []
                || ProtocolTemplate::query()->where('organization_id', $organization->id)->where('name', $name)->exists()) {
                $skipped++;

                continue;
            }

            ProtocolTemplate::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'kind' => $kind->value,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'items' => $items,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
