<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeAddressAdoptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Contacts\ContactAddress;
use App\Models\Customer\Customer;
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\Services\LexofficeContactSync;
use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1702: „Lexoffice gewinnt“ ordnet lokal angelegte Anschriften ohne
 * Fremd-ID nach Art und Primär-Kennzeichen den Lexoffice-Anschriften zu, statt
 * eine zweite anzulegen. Danach ist nur die erste Rechnungsanschrift aus
 * Lexoffice primär, und die Projektion zeigt sie.
 */
final class LexofficeAddressAdoptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const string VAT_ID = 'DE111111125';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    public function test_local_addresses_receive_the_lexoffice_ids_instead_of_duplicates(): void {
        $customer = $this->customer();
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Lokalweg 1', 'zip' => '12345', 'city' => 'Altstadt', 'country' => 'DE']);
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);
        $this->fakeContact();

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $customer->refresh();
        $this->assertSame(2, $customer->addresses()->count(), 'Keine zweite Rechnungs- oder Lieferanschrift.');
        $billing = $customer->addresses()->where('kind', ContactAddress::KIND_BILLING)->firstOrFail();
        $this->assertSame(['billing-0', 'Hauptstr. 1', true], [$billing->external_id, $billing->street, $billing->is_primary]);
        $shipping = $customer->addresses()->where('kind', ContactAddress::KIND_SHIPPING)->firstOrFail();
        $this->assertSame(['shipping-0', 'Lieferung 9', false], [$shipping->external_id, $shipping->street, $shipping->is_primary]);
        $this->assertSame('Hauptstr. 1', $customer->address_street, 'Lexoffice gewinnt: die Projektion zeigt die Lexoffice-Anschrift.');

        // Ein zweiter Abgleich (neue Lexoffice-Version) findet die Anschriften über die Fremd-ID.
        $this->fakeContact(version: 2);
        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);
        $this->assertSame(2, $customer->addresses()->count());
    }

    /** Eine weitere lokale Rechnungsanschrift (etwa nach einem Zusammenführen) bleibt, aber nicht primär. */
    public function test_only_the_first_lexoffice_billing_address_stays_primary(): void {
        $customer = $this->customer();
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Lokalweg 1', 'city' => 'Altstadt']);
        $customer->addresses()->create(['organization_id' => $this->organization->id, 'kind' => ContactAddress::KIND_BILLING, 'street' => 'Historie 7', 'city' => 'Altstadt', 'is_primary' => false]);
        $this->fakeContact(withShipping: false);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $customer->refresh();
        $billing = $customer->addresses()->where('kind', ContactAddress::KIND_BILLING)->orderBy('id')->get();
        $this->assertCount(2, $billing);
        $this->assertSame([['billing-0', 'Hauptstr. 1', true], [null, 'Historie 7', false]], $billing->map(static fn (ContactAddress $a): array => [$a->external_id, $a->street, $a->is_primary])->all());
        $this->assertSame('Hauptstr. 1', $customer->address_street);
    }

    /** Gegenprobe: Altbestand ohne Satellit bekommt genau eine Anschrift aus Lexoffice. */
    public function test_legacy_customer_without_satellite_gets_a_single_address(): void {
        $customer = $this->customer(['address_street' => 'Inlineweg 2', 'address_city' => 'Altstadt']);
        $this->assertNull($customer->primaryAddress());
        $this->fakeContact(withShipping: false);

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LexofficeWins);

        $customer->refresh();
        $this->assertSame(1, $customer->addresses()->count());
        $this->assertSame('Hauptstr. 1', $customer->address_street);
    }

    /** Gegenprobe zur Policy: „Lokal gewinnt“ fasst die Anschriften nicht an. */
    public function test_local_wins_leaves_local_addresses_alone(): void {
        $customer = $this->customer();
        app(ContactDetailsWriter::class)->writeAddress($customer, ['street' => 'Lokalweg 1', 'city' => 'Altstadt']);
        $this->fakeContact();

        (new LexofficeContactSync)->sync($this->organization, LexofficeMatchPolicy::LocalWins);

        $customer->refresh();
        $this->assertSame(1, $customer->addresses()->count());
        $this->assertNull($customer->primaryAddress()?->external_id);
        $this->assertSame('Lokalweg 1', $customer->address_street);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Abgleich GmbH',
            'company' => 'Abgleich GmbH',
            'vat_id' => self::VAT_ID,
            'email' => 'info@abgleich.test',
        ]);
    }

    private function fakeContact(bool $withShipping = true, int $version = 1): void {
        $addresses = ['billing' => [['street' => 'Hauptstr. 1', 'zip' => '10115', 'city' => 'Berlin', 'countryCode' => 'DE']]];
        if ($withShipping) {
            $addresses['shipping'] = [['street' => 'Lieferung 9', 'zip' => '20095', 'city' => 'Hamburg', 'countryCode' => 'DE']];
        }
        $contact = [
            'id' => 'lex-abgleich',
            'version' => $version,
            'company' => ['name' => 'Abgleich GmbH', 'vatRegistrationId' => self::VAT_ID],
            'emailAddresses' => ['business' => ['info@abgleich.test']],
            'addresses' => $addresses,
        ];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts*' => static fn () => FakePluginHttp::response(['content' => [$contact], 'totalPages' => 1], 200),
        ]);
    }
}
