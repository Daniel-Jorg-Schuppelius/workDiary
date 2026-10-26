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

    public function purge(Organization $organization): void {
        \App\Models\Rental\RentalTenancy::query()->where('organization_id', $organization->id)->delete();
    }

    private function seedRental(Organization $organization, ?User $actor): int {
        if (! $this->context->moduleActive('module.rental')) {
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
            $housing = $this->context->industry === \App\Enums\Demo\DemoIndustry::Vermieter;

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
                'rental_mode' => $housing ? \App\Enums\Rental\RentalMode::Holiday : null,
                'portal_bookable' => $housing,
                'public_description' => $housing ? 'Ferienwohnung mit Balkon und Seeblick.' : null,
                'max_guests' => 4,
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

            if ($housing) {
                $assets = app(\App\Services\Asset\AssetService::class);
                foreach (['Wohnung Stadtgarten', 'Ferienwohnung Sonnenterrasse'] as $index => $name) {
                    $unit = $assets->create($actor, ['name' => $name, 'asset_class' => \App\Enums\Asset\AssetClass::Other->value, 'location_text' => 'Musterstraße ' . ($index + 2)]);
                    $profile = \App\Models\Rental\RentalProfile::query()->create([
                        'organization_id' => $organization->id, 'asset_id' => $unit->id,
                        'rental_mode' => $index === 0 ? \App\Enums\Rental\RentalMode::LongTerm : \App\Enums\Rental\RentalMode::Holiday,
                        'is_rentable' => true, 'portal_bookable' => true, 'rent_amount' => '850.00', 'ancillary_amount' => '180.00',
                        'default_rate_card_id' => $card->id,
                    ]);
                    if ($index === 0) {
                        app(\App\Services\Rental\RentalTenancyService::class)->create($profile, $customer, $actor, ['starts_on' => now()->startOfMonth()->toDateString()]);
                    }
                }
            }

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Verleih-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}
