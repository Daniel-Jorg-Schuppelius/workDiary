<?php
/*
 * Created on   : Mon Nov 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalPrepaymentPlaceOfSupplyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{OnlinePaymentStatus, PlaceOfSupplyKind};
use App\Enums\Rental\{RentalChargeKind, RentalRateCardStatus, RentalRequestStatus};
use App\Models\Article\Article;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Invoicing\OnlinePayment;
use App\Models\Platform\User;
use App\Models\Rental\{RentalProfile, RentalRateCard, RentalRequest};
use App\Services\Accounting\Posting\PrepaymentAdvance;
use App\Services\Invoicing\ServiceDefaultResolver;
use App\Services\Rental\{RentalBillingService, RentalCaseService};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1717: Die Online-Vorauszahlung einer Direktbuchung rechnet ihre
 * Umsatzsteuer nach dem Leistungsort der Standardleistung — wie die Rechnung
 * der Akte (MVP-1704) —, nicht nach der Vorgabe der Organisation. Dasselbe
 * gilt für den Satz, mit dem die Buchhaltung die Anzahlung bucht.
 *
 * Preisliste: 3 Tagessätze à 100 € + Endreinigung 50 € = 350 € netto,
 * Vorauszahlung 50 %.
 */
final class RentalPrepaymentPlaceOfSupplyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Asset $asset;

    private RentalProfile $profile;

    private Carbon $from;

    private Carbon $to;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 09:00:00');
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger']);
        $card = RentalRateCard::query()->create(['organization_id' => $this->organization->id, 'name' => 'Standard', 'version' => 1, 'status' => RentalRateCardStatus::Active->value, 'created_by' => $this->admin->id]);
        $card->items()->createMany([
            ['organization_id' => $this->organization->id, 'kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'group_code' => 'bagger', 'amount' => '100.00', 'unit' => 'day'],
            ['organization_id' => $this->organization->id, 'kind' => 'cleaning', 'label' => 'Endreinigung', 'amount' => '50.00', 'unit' => 'flat'],
        ]);
        $this->profile = RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $this->asset->id, 'is_rentable' => true, 'portal_bookable' => true, 'group_code' => 'bagger', 'default_rate_card_id' => $card->id]);
        $this->organization->forceFill(['settings' => array_replace_recursive((array) $this->organization->settings, [
            'rental' => ['portal_direct_booking' => true, 'portal_prepayment_percent' => 50],
        ])])->save();
        $this->from = Carbon::parse('2026-10-07 08:00');
        $this->to = Carbon::parse('2026-10-09 17:00');
    }

    /** Grundstücksleistung (Beherbergung) im Inland an ein Unternehmen in der Schweiz: deutsche Steuer. */
    public function test_property_service_charges_german_vat_to_a_business_abroad(): void {
        $this->standardService(PlaceOfSupplyKind::Property);
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => false]);

        $this->assertSame('208.25', app(RentalBillingService::class)->prepaymentAmount($this->profile, $customer, $this->from, $this->to)?->getAmount());
        $this->assertSame('19.00', $this->advanceRate($customer));

        // Dieselbe Steuer wie die Rechnung der Akte.
        $this->assertSame('19.00', $this->invoiceRateOfCase($customer));
    }

    /** Katalogleistung an einen Verbraucher in der Schweiz: nicht steuerbar, die Vorauszahlung trägt keine Steuer. */
    public function test_catalog_service_to_a_consumer_abroad_carries_no_vat(): void {
        $this->standardService(PlaceOfSupplyKind::Catalog);
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);

        $this->assertSame('175.00', app(RentalBillingService::class)->prepaymentAmount($this->profile, $customer, $this->from, $this->to)?->getAmount());
        $this->assertSame('0.00', $this->advanceRate($customer));
        $this->assertSame('0.00', $this->invoiceRateOfCase($customer));
    }

    /** Gegenprobe: Ohne gepflegten Leistungsort bleibt es bei der Vorgabe der Organisation („Allgemein“). */
    public function test_without_place_of_supply_the_amount_stays_as_before(): void {
        $business = $this->customer(['country' => 'CH', 'is_consumer' => false]);
        $consumer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $billing = app(RentalBillingService::class);

        $this->assertSame('175.00', $billing->prepaymentAmount($this->profile, $business, $this->from, $this->to)?->getAmount(), 'Unternehmen im Drittland: nicht steuerbar.');
        $this->assertSame('208.25', $billing->prepaymentAmount($this->profile, $consumer, $this->from, $this->to)?->getAmount(), 'Verbraucher im Drittland: deutsche Steuer (§ 3a Abs. 1 UStG).');
        $this->assertSame('0.00', $this->advanceRate($business));
        $this->assertSame('19.00', $this->advanceRate($consumer));
    }

    /** Satz, mit dem die Buchhaltung die bezahlte Vorauszahlung der Direktbuchung als Anzahlung bucht. */
    private function advanceRate(Customer $customer): ?string {
        $request = RentalRequest::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'asset_id' => $this->asset->id,
            'starts_at' => $this->from,
            'ends_at' => $this->to,
            'status' => RentalRequestStatus::Accepted->value,
            'is_direct' => true,
        ]);
        $payment = OnlinePayment::query()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'provider' => 'fake',
            'status' => OnlinePaymentStatus::Paid,
            'currency' => 'EUR',
            'gross_amount' => Money::of('100.00', CurrencyCode::Euro),
            'refunded_amount' => Money::zero(CurrencyCode::Euro),
            'paid_at' => now(),
        ]);

        return app(PrepaymentAdvance::class)->party($this->organization, $payment)['rate'] ?? null;
    }

    /** Kopfsatz der Rechnung, die der Abrechnungslauf aus der Akte der Direktbuchung (ohne Projekt) anlegt. */
    private function invoiceRateOfCase(Customer $customer): ?string {
        $case = app(RentalCaseService::class)->open($this->organization, $this->admin, [
            'customer_id' => $customer->id,
            'starts_at' => $this->from,
            'ends_at' => $this->to,
        ]);
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => RentalChargeKind::DailyRate->value, 'label' => 'Tagessatz', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);

        return $billing->invoiceReleasedCharges($case, $this->admin)?->refresh()->tax_rate?->getNumericValue();
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Mieter ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'mieter@kunde.example',
            'address_street' => 'Seeweg 4',
            'address_zip' => '8001',
            'address_city' => 'Zürich',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    private function standardService(PlaceOfSupplyKind $kind): void {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ArticleType::Service->value,
            'base_unit' => 'Tag',
            'place_of_supply_kind' => $kind->value,
        ]);
        $this->organization->forceFill(['settings' => array_replace_recursive(
            (array) $this->organization->settings,
            ['invoicing' => ['default_service_article' => 'art:' . $article->id]],
        )])->save();
        app(ServiceDefaultResolver::class)->flush();
    }
}
