<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactProjectionReconcileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Stammdaten;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1725: `contacts:reconcile-projection` gleicht Altbestand an, dessen
 * Projektion vom primären Satelliten abweicht. Ohne Satellit wird die Projektion
 * übernommen, ein jüngerer Satellit gilt, sonst die Projektion (zuletzt direkt
 * geschrieben) — ihre leeren Felder behalten den Wert des Satelliten.
 */
final class ContactProjectionReconcileTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_dry_run_reports_and_writes_nothing(): void {
        $customer = $this->customerWithSatellite();
        $this->driftProjection($customer, ['address_city' => 'Neustadt'], partyNewer: true);

        $this->artisan('contacts:reconcile-projection', ['--dry-run' => true, '--organization' => $this->organization->id])
            ->expectsOutputToContain('Customer #' . $customer->id . ' · Anschrift: address_city')
            ->expectsOutputToContain('1 Abweichung(en) gefunden')
            ->assertSuccessful();

        $this->assertSame('Altstadt', $customer->primaryAddress()?->city, 'Probelauf schreibt nichts.');
        $this->assertSame('Neustadt', $customer->fresh()?->address_city);
    }

    public function test_projection_written_after_the_satellite_wins_and_empty_fields_keep_the_satellite(): void {
        $customer = $this->customerWithSatellite();
        // Direkter Schreibweg vor MVP-1700: Ort geändert, Land beim Teil-Update geleert.
        $this->driftProjection($customer, ['address_city' => 'Neustadt', 'country' => null], partyNewer: true);

        $this->artisan('contacts:reconcile-projection')->assertSuccessful();

        $customer->refresh();
        $address = $customer->primaryAddress();
        $this->assertSame(['Altweg 1', 'Neustadt', 'DE'], [$address?->street, $address?->city, $address?->country_code]);
        $this->assertSame(['Neustadt', 'DE'], [$customer->address_city, $customer->country], 'Die Projektion folgt dem angeglichenen Satelliten.');
        $this->assertSame(1, $customer->addresses()->count());
    }

    public function test_satellite_changed_after_the_party_wins(): void {
        $customer = $this->customerWithSatellite();
        $this->driftProjection($customer, ['address_city' => 'Veraltet'], partyNewer: false);

        $this->artisan('contacts:reconcile-projection')->assertSuccessful();

        $this->assertSame('Altstadt', $customer->fresh()?->address_city);
        $this->assertSame('Altstadt', $customer->primaryAddress()?->city);
    }

    public function test_legacy_party_without_satellite_gets_it_from_the_projection(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'address_street' => 'Lagerweg 2',
            'address_zip' => '54321',
            'address_city' => 'Musterstadt',
            'country' => 'AT',
            'bank_iban' => 'DE89370400440532013000',
        ]);
        $this->assertSame(0, $supplier->addresses()->count(), 'Altbestand: nur Projektion.');

        $this->artisan('contacts:reconcile-projection')->assertSuccessful();

        $address = $supplier->primaryAddress();
        $bank = $supplier->primaryBankAccount();
        $this->assertSame(['Lagerweg 2', '54321', 'Musterstadt', 'AT'], [$address?->street, $address?->zip, $address?->city, $address?->country_code]);
        $this->assertSame('DE89370400440532013000', $bank?->iban);
        $this->assertSame('DE89370400440532013000', $supplier->fresh()?->bank_iban);

        // Wiederholt: nichts mehr abweichend.
        $this->artisan('contacts:reconcile-projection')->expectsOutputToContain('0 Abweichung(en) angeglichen')->assertSuccessful();
    }

    public function test_spelling_of_identifiers_is_no_drift(): void {
        $customer = $this->customerWithSatellite();
        // Gegenprobe: gleiche Werte in anderer Schreibweise.
        $this->assertSame([], app(ContactDetailsWriter::class)->reconcileProjection($customer, apply: false));
        DB::table('customers')->where('id', $customer->id)->update(['country' => 'de']);

        $this->assertSame([], app(ContactDetailsWriter::class)->reconcileProjection($customer->fresh() ?? $customer, apply: false));
    }

    private function customerWithSatellite(): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_street' => null, 'address_zip' => null, 'address_city' => null, 'country' => null]);
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Altweg 1', 'zip' => '12345', 'city' => 'Altstadt', 'country' => 'DE']);

        return $customer->refresh();
    }

    /**
     * Projektion am Satelliten vorbei ändern; die Zeitstempel bestimmen, wer jünger ist.
     *
     * @param  array<string, ?string>  $columns
     */
    private function driftProjection(Customer $customer, array $columns, bool $partyNewer): void {
        $address = $customer->primaryAddress();
        $this->assertNotNull($address);
        DB::table('customers')->where('id', $customer->id)->update($columns + ['updated_at' => $partyNewer ? now()->addMinute() : now()->subHour()]);
        DB::table('contact_addresses')->where('id', $address->id)->update(['updated_at' => now()]);
    }
}
