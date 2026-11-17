<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactMergePrimaryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Stammdaten;

use App\Models\Contacts\{ContactAddress, ContactBankAccount};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Stammdaten\{ContactDetailsWriter, CustomerMergeService, SupplierMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1701: Nach dem Zusammenführen trägt das Ziel genau eine primäre
 * Rechnungsanschrift und Bankverbindung — die eigene, ausgewählt die der
 * Quelle. Die andere bleibt als weitere stehen, eine gleichlautende entfällt;
 * die Projektion folgt der primären.
 */
final class ContactMergePrimaryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_target_keeps_its_address_and_the_source_address_stays_as_history(): void {
        $target = $this->customer('Ziel GmbH', 'Zielweg 1', 'DE89370400440532013000');
        $source = $this->customer('Quelle GmbH', 'Quellweg 2', 'DE02120300000000202051');

        app(CustomerMergeService::class)->merge($source, $target);

        $target->refresh();
        $this->assertSame(1, $this->primaryAddresses($target), 'Genau eine primäre Rechnungsanschrift.');
        $this->assertSame(2, $target->addresses()->where('kind', '!=', ContactAddress::KIND_SHIPPING)->count(), 'Die Anschrift der Quelle bleibt als weitere.');
        $this->assertSame('Zielweg 1', $target->primaryAddress()?->street);
        $this->assertSame('Zielweg 1', $target->address_street);
        $this->assertSame(1, $this->primaryBankAccounts($target));
        $this->assertSame('DE89370400440532013000', $target->bank_iban);
    }

    public function test_selected_source_address_and_bank_account_win(): void {
        $target = $this->customer('Ziel GmbH', 'Zielweg 1', 'DE89370400440532013000', ['address_lat' => '52.5200000', 'address_lng' => '13.4050000']);
        $source = $this->customer('Quelle GmbH', 'Quellweg 2', 'DE02120300000000202051', ['address_lat' => '48.1351000', 'address_lng' => '11.5820000']);

        $this->actingAs($this->admin)->get(route('customers.duplicates.compare', ['source' => $source->sqid, 'target' => $target->sqid]))
            ->assertOk()
            ->assertSee('value="' . ContactDetailsWriter::MERGE_BILLING_ADDRESS . '"', false)
            ->assertSee('value="' . ContactDetailsWriter::MERGE_BANK_ACCOUNT . '"', false)
            ->assertDontSee('value="address_street"', false)
            ->assertSee('Quellweg 2, 12345 Altstadt, DE');

        $this->actingAs($this->admin)->post(route('customers.duplicates.merge'), [
            'source' => $source->sqid,
            'target' => $target->sqid,
            'prefer_source' => [ContactDetailsWriter::MERGE_BILLING_ADDRESS, ContactDetailsWriter::MERGE_BANK_ACCOUNT],
        ])->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(1, $this->primaryAddresses($target));
        $this->assertSame('Quellweg 2', $target->primaryAddress()?->street);
        $this->assertSame('Quellweg 2', $target->address_street, 'Die Projektion folgt der primären Anschrift.');
        $this->assertSame(1, $this->primaryBankAccounts($target));
        $this->assertSame('DE02120300000000202051', $target->bank_iban);
        $this->assertSame('48.1351000', (string) $target->address_lat, 'Die Koordinaten folgen der geltenden Anschrift.');
    }

    public function test_target_without_address_takes_the_source_address(): void {
        $target = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Leer GmbH', 'currency' => 'EUR']);
        $source = $this->customer('Quelle GmbH', 'Quellweg 2', 'DE02120300000000202051');

        app(CustomerMergeService::class)->merge($source, $target);

        $target->refresh();
        $this->assertSame('Quellweg 2', $target->address_street);
        $this->assertSame('DE02120300000000202051', $target->bank_iban);
        $this->assertSame(1, $this->primaryAddresses($target));
    }

    public function test_an_identical_source_address_is_not_kept_twice(): void {
        $target = $this->customer('Ziel GmbH', 'Gleichweg 3', 'DE89370400440532013000');
        $source = $this->customer('Quelle GmbH', 'Gleichweg 3', 'DE89370400440532013000');

        app(CustomerMergeService::class)->merge($source, $target);

        $target->refresh();
        $this->assertSame(1, $target->addresses()->count());
        $this->assertSame(1, $target->bankAccounts()->count());
        $this->assertSame('Gleichweg 3', $target->address_street);
    }

    /** Fremd-IDs der Anschriften bleiben am Ziel eindeutig, sonst wüsste der Abgleich nicht, welche er meint. */
    public function test_colliding_external_address_ids_stay_with_the_target(): void {
        $target = $this->customer('Ziel GmbH', 'Zielweg 1', 'DE89370400440532013000');
        $source = $this->customer('Quelle GmbH', 'Quellweg 2', 'DE02120300000000202051');
        foreach ([$target, $source] as $party) {
            app(ContactDetailsWriter::class)->adoptProjection($party);
            $party->primaryAddress()?->forceFill(['external_id' => 'billing-0'])->save();
        }

        app(CustomerMergeService::class)->merge($source, $target);

        $target->refresh();
        $this->assertSame(1, $target->addresses()->where('external_id', 'billing-0')->count());
        $this->assertSame('Zielweg 1', $target->addresses()->where('external_id', 'billing-0')->first()?->street);
    }

    public function test_supplier_merge_keeps_one_primary_address_and_bank_account(): void {
        $target = $this->supplier('Ziel Handel', 'Zielweg 1', 'DE89370400440532013000');
        $source = $this->supplier('Quelle Handel', 'Quellweg 2', 'DE02120300000000202051');

        app(SupplierMergeService::class)->merge($source, $target, [ContactDetailsWriter::MERGE_BANK_ACCOUNT => null]);

        $target->refresh();
        $this->assertSame(1, $this->primaryAddresses($target));
        $this->assertSame('Zielweg 1', $target->address_street);
        $this->assertSame(1, $this->primaryBankAccounts($target));
        $this->assertSame('DE02120300000000202051', $target->bank_iban, 'Ausgewählt gilt die Bankverbindung der Quelle.');

        $this->actingAs($this->admin)->get(route('suppliers.duplicates.compare', ['source' => $this->supplier('Dritte', 'Drittweg 3', 'DE89370400440532013000')->sqid, 'target' => $target->sqid]))
            ->assertOk()
            ->assertSee('value="' . ContactDetailsWriter::MERGE_BILLING_ADDRESS . '"', false)
            ->assertDontSee('value="bank_iban"', false);
    }

    /** @param array<string, mixed> $extra */
    private function customer(string $name, string $street, string $iban, array $extra = []): Customer {
        return Customer::factory()->create($extra + [
            'organization_id' => $this->organization->id,
            'name' => $name,
            'address_street' => $street,
            'address_zip' => '12345',
            'address_city' => 'Altstadt',
            'country' => 'DE',
            'bank_iban' => $iban,
            'bank_bic' => null,
            'bank_account_holder' => null,
            'vat_id' => null,
            'email' => null,
        ]);
    }

    private function supplier(string $name, string $street, string $iban): Supplier {
        return Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'address_street' => $street,
            'address_zip' => '12345',
            'address_city' => 'Altstadt',
            'country' => 'DE',
            'bank_iban' => $iban,
        ]);
    }

    private function primaryAddresses(Customer|Supplier $party): int {
        return $party->addresses()->where('kind', '!=', ContactAddress::KIND_SHIPPING)->where('is_primary', true)->count();
    }

    private function primaryBankAccounts(Customer|Supplier $party): int {
        return ContactBankAccount::query()->where('accountable_type', $party->getMorphClass())->where('accountable_id', $party->id)->where('is_primary', true)->count();
    }
}
