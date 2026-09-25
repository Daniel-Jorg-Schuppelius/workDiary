<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDefectWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset\EarlyWarnings;

use App\Models\Asset\Asset;
use App\Models\Platform\Organization;
use App\Services\Asset\RecurringDefectService;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use Carbon\CarbonImmutable;

/** Objekte mit wiederkehrenden Defekten im Zwölf-Monats-Fenster (Feature 009, MVP-889). */
final class AssetDefectWarningSource implements EarlyWarningSource {
    public function __construct(private readonly RecurringDefectService $defects) {}

    public function key(): string {
        return 'asset-defects';
    }

    public function warnings(Organization $organization): array {
        $to = CarbonImmutable::now();
        $warnings = [];
        foreach ($this->defects->pareto((int) $organization->id, $to->subMonths(RecurringDefectService::WINDOW_MONTHS), $to) as $row) {
            $asset = $row['is_recurring'] ? Asset::query()->find($row['asset_id']) : null;
            if ($asset === null) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: 'asset_defects',
                subject: $asset,
                title: 'reporting.warning.asset_defects.title',
                detail: 'reporting.warning.asset_defects.detail',
                recommendation: 'reporting.warning.asset_defects.recommendation',
                url: route('assets.show', $asset),
                params: ['name' => $row['asset_name'], 'count' => $row['recent_total'], 'months' => RecurringDefectService::WINDOW_MONTHS],
            );
        }

        return $warnings;
    }
}
