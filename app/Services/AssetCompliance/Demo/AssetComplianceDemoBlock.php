<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Prüfmittel-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class AssetComplianceDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'asset_compliance' => $this->seedAssetCompliance($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    private function seedAssetCompliance(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.asset_compliance')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $asset = \App\Models\Asset\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $profile = \App\Models\AssetCompliance\AssetComplianceProfile::query()
                ->whereNull('organization_id')
                ->where('code', 'dguv_v3_portable')
                ->first();
            if ($asset === null || $profile === null) {
                return 0;
            }

            $service = app(\App\Services\AssetCompliance\AssetComplianceService::class);
            $assignment = $service->assign($profile, $asset, $actor, [
                'last_done_on' => now()->subMonths(11)->toDateString(),
                'responsible_user_id' => $actor->id,
            ]);

            $service->recordInspection($assignment, $actor, [
                'result' => 'passed',
                'note' => (string) __('Demo-Prüfung ohne Befund.'),
                'signature_name' => $actor->name,
                'certificate' => [
                    'certificate_no' => 'KAL-2026-0001',
                    'issuer' => (string) __('Demo-Prüfstelle GmbH'),
                    'issued_on' => now()->toDateString(),
                    'valid_until' => now()->addYear()->toDateString(),
                ],
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Prüfmittel-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}
