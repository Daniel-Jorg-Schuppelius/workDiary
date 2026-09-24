<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetFinance\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Leasing-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class AssetFinanceDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'asset_finance' => $this->seedAssetFinance($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    private function seedAssetFinance(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.asset_finance')) {
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
            if ($asset === null) {
                return 0;
            }

            $service = app(\App\Services\AssetFinance\AssetFinanceService::class);
            $contract = $service->create($organization, $actor, [
                'kind' => \App\Enums\AssetFinance\AssetFinanceKind::OperatingLease->value,
                'partner_name' => (string) __('Muster-Leasing GmbH (Demo)'),
                'contract_no' => 'ML-2026-0042',
                'starts_on' => now()->startOfMonth()->toDateString(),
                'ends_on' => now()->startOfMonth()->addMonths(11)->toDateString(),
                'payment_rhythm' => 'monthly',
                'rate_amount' => '390.00',
                'residual_value' => '4500.00',
                'responsible_user_id' => $actor->id,
                'notes' => (string) __('Demo-Leasingakte mit Ratenplan und Kündigungsfrist.'),
            ], [$asset->id]);
            $service->activate($contract, $actor);

            $contract->deadlines()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceDeadlineKind::Termination->value,
                'due_on' => now()->addMonths(8)->toDateString(),
                'warn_days_before' => 60,
                'responsible_user_id' => $actor->id,
                'note' => (string) __('Kündigung spätestens 3 Monate vor Vertragsende (Demo).'),
            ]);
            $contract->usageLimits()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceUsageLimitKind::OperatingHours->value,
                'limit_value' => '1200.00',
                'period' => 'yearly',
                'overrun_fee_per_unit' => '2.5000',
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Leasing-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}
