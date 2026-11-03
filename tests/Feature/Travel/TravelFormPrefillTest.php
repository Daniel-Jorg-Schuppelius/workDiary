<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelFormPrefillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Travel\{PerDiemRate, PerDiemTrip};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fahrtenbuch und Reisekosten: Die Kundenoption trägt Ziel bzw. Ort und Land
 * (Vorbelegung aus der Elternauswahl, Pilot 2026-11-03); der Reisekosten-
 * Request nimmt ohne Ort den Ort des Kunden, eigene Angaben bleiben.
 */
class TravelFormPrefillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgAdmin();
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'address_street' => 'Musterweg 7',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'country' => 'at',
        ]);
        // Der Speichervorgang braucht einen Verpflegungssatz des Landes am Reisetag.
        PerDiemRate::factory()->create(['country' => 'DE']);
    }

    public function test_travel_log_form_offers_the_customer_address_as_destination(): void {
        $html = $this->actingAs($this->user)->get(route('travel-logs.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-to-address="Musterweg 7, 12345 Musterstadt"', $html);
        $this->assertMatchesRegularExpression('/name="to_address"[^>]*data-prefill-from="customer_id"[^>]*data-prefill-key="to-address"[^>]*data-prefill-on-load/s', $html);
        $this->assertDoesNotMatchRegularExpression('/name="from_address"[^>]*data-prefill-from/s', $html);
    }

    public function test_per_diem_form_offers_city_and_country_of_the_customer(): void {
        $html = $this->actingAs($this->user)->get(route('per-diem-trips.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-location="Musterstadt" data-prefill-country="AT"', $html);
        $this->assertMatchesRegularExpression('/name="location"[^>]*data-prefill-from="customer_id"[^>]*data-prefill-guard/s', $html);
        $this->assertMatchesRegularExpression('/name="country"[^>]*data-prefill-from="customer_id"/s', $html);
    }

    public function test_per_diem_store_falls_back_to_the_customer_city(): void {
        $this->actingAs($this->user)->post(route('per-diem-trips.store'), $this->payload(['location' => ' ']))->assertSessionHasNoErrors();

        $trip = PerDiemTrip::query()->latest('id')->firstOrFail();
        $this->assertSame('Musterstadt', $trip->location);
        $this->assertSame('DE', $trip->country, 'Das eigene Land bleibt, der Kunde liefert nur den fehlenden Ort.');
    }

    public function test_per_diem_store_keeps_an_own_location_and_rejects_none_without_customer(): void {
        $this->actingAs($this->user)->post(route('per-diem-trips.store'), $this->payload(['location' => 'Messe Nord']))->assertSessionHasNoErrors();
        $this->assertSame('Messe Nord', PerDiemTrip::query()->latest('id')->firstOrFail()->location);

        $this->actingAs($this->user)->post(route('per-diem-trips.store'), $this->payload(['location' => '', 'customer_id' => '']))
            ->assertSessionHasErrors(['location']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'country' => 'DE',
            'purpose' => 'Kundentermin',
            'customer_id' => $this->customer->sqid,
            'started_at' => '2030-03-10T08:00',
            'ended_at' => '2030-03-10T18:30',
        ], $overrides);
    }
}
