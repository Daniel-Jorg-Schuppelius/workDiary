<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental\Demo;

use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Verleih-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class RentalDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'rental' => $this->seedRental($context->organization, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    private function seedRental(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.rental')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $asset = \App\Models\Asset\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null || $asset === null) {
                return 0;
            }

            // Versionierte Preisliste (D10) mit Tagessatz + Reinigung.
            $card = \App\Models\Rental\RentalRateCard::query()->create([
                'organization_id' => $organization->id,
                'name' => (string) __('Standard-Verleih (Demo)'),
                'version' => 1,
                'status' => \App\Enums\Rental\RentalRateCardStatus::Active->value,
                'valid_from' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $card->items()->createMany([
                ['organization_id' => $organization->id, 'kind' => 'daily_rate', 'label' => (string) __('Tagessatz (Demo)'), 'amount' => '45.00', 'unit' => 'day'],
                ['organization_id' => $organization->id, 'kind' => 'cleaning', 'label' => (string) __('Endreinigung (Demo)'), 'amount' => '25.00', 'unit' => 'flat'],
            ]);

            \App\Models\Rental\RentalProfile::query()->create([
                'organization_id' => $organization->id,
                'asset_id' => $asset->id,
                'is_rentable' => true,
                'group_code' => 'demo',
                'buffer_after_hours' => 2,
                'default_rate_card_id' => $card->id,
            ]);

            $service = app(\App\Services\Rental\RentalCaseService::class);
            $case = $service->open($organization, $actor, [
                'customer_id' => $customer->id,
                'starts_at' => now()->addDay()->setTime(8, 0),
                'ends_at' => now()->addDays(3)->setTime(17, 0),
                'responsible_user_id' => $actor->id,
                'rental_rate_card_id' => $card->id,
                'deposit_amount' => '150.00',
                'notes' => (string) __('Demo-Verleihvorgang mit Konditionen-Snapshot.'),
            ], [$asset->id]);
            $service->reserve($case, $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Verleih-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}
