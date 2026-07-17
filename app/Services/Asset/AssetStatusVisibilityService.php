<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusVisibilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\AssetStatus;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Protocol\ProtocolType;
use App\Models\Asset;

class AssetStatusVisibilityService {
    /**
     * @return array<string, mixed>
     */
    public function summarize(Asset $asset): array {
        $openStatuses = OpenIssueStatus::openValues();

        $openIssues = $asset->openIssues()->whereIn('status', $openStatuses);
        $openIssueCount = (clone $openIssues)->count();
        $blockedIssueCount = (clone $openIssues)->where('status', OpenIssueStatus::Blocked->value)->count();
        $criticalIssueCount = (clone $openIssues)->where('severity', 'critical')->count();

        $defectProtocols = $asset->protocols()->where('type', ProtocolType::Defect->value);
        $defectProtocolCount = (clone $defectProtocols)->count();
        $lastDefectProtocolAt = (clone $defectProtocols)->value('occurred_at');

        $isBlocked = $asset->status === AssetStatus::Blocked || $blockedIssueCount > 0;
        $hasDefect = $openIssueCount > 0 || $defectProtocolCount > 0;

        return [
            'asset_id' => $asset->id,
            'status' => $asset->status->value,
            'is_blocked' => $isBlocked,
            'has_defect' => $hasDefect,
            'attention_level' => $this->attentionLevel($isBlocked, $criticalIssueCount),
            'open_issues' => [
                'total' => $openIssueCount,
                'blocked' => $blockedIssueCount,
                'critical' => $criticalIssueCount,
            ],
            'defect_protocols' => [
                'total' => $defectProtocolCount,
                'latest_occurred_at' => $lastDefectProtocolAt?->toISOString(),
            ],
        ];
    }

    private function attentionLevel(bool $isBlocked, int $criticalIssueCount): string {
        if ($criticalIssueCount > 0) {
            return 'critical';
        }

        if ($isBlocked) {
            return 'warning';
        }

        return 'normal';
    }
}
