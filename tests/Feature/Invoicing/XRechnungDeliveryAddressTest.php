<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungDeliveryAddressTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{InvoiceStatus, XRechnungSyntax};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use App\Services\Stammdaten\ContactDetailsWriter;
use ERechnungToolkit\Enums\ERechnungProfile;
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\KositValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1689: Bei innergemeinschaftlicher Lieferung (K) nennt die E-Rechnung die
 * eingefrorene Lieferanschrift des Kunden als Lieferpartei mit Lieferland
 * (BG-13/BG-15, BT-70, BT-75–BT-80), ohne sie die Rechnungsanschrift; die
 * Prüfung vor dem Versand prüft die tatsächlich genutzte Anschrift.
 */
final class XRechnungDeliveryAddressTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_intra_community_supply_names_the_different_delivery_address(): void {
        $customer = $this->customer();
        $this->ship($customer, ['street' => 'Lagerweg 9', 'supplement' => 'Tor 3', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);
        $invoice = $this->issued($customer);
        $generator = app(XRechnungGenerator::class);

        $this->assertSame([], $generator->preflight($invoice)['errors']);
        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = $generator->generate($invoice, $syntax);

            $delivery = (new ERechnungParser)->parse($xml)->getDeliveryParty();
            $address = $delivery?->getPostalAddress();
            $this->assertSame('Linz', $address?->getCity(), $syntax->value);
            $this->assertSame('4020', $address->getPostalCode(), $syntax->value);
            $this->assertSame('Lagerweg 9', $address->getStreetName(), $syntax->value);
            $this->assertSame('Tor 3', $address->getAdditionalStreetName(), $syntax->value);
            $this->assertSame('AT', $address->getCountryCode(), $syntax->value);
            $this->assertSame('Kunde Beispiel GmbH', $delivery->getName(), $syntax->value);
            // Die Käuferanschrift (BG-8) bleibt die Rechnungsanschrift.
            $this->assertSame('Kundenstadt', (new ERechnungParser)->parse($xml)->getBuyer()->getPostalAddress()?->getCity(), $syntax->value);
            $this->assertKositAccepted($xml);
        }
    }

    /** Gegenprobe: ohne Lieferanschrift bleibt die Rechnungsanschrift die Lieferanschrift. */
    public function test_without_delivery_address_the_billing_address_is_used(): void {
        $invoice = $this->issued($this->customer());

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $address = (new ERechnungParser)->parse(app(XRechnungGenerator::class)->generate($invoice, $syntax))->getDeliveryParty()?->getPostalAddress();
            $this->assertSame('Kundenstadt', $address?->getCity(), $syntax->value);
            $this->assertSame('AT', $address->getCountryCode(), $syntax->value);
            $this->assertNull($address->getAdditionalStreetName(), $syntax->value);
        }
    }

    public function test_preflight_checks_the_address_actually_used(): void {
        $message = (string) __('invoicing.einvoice.error.intra_community_delivery_address');
        $generator = app(XRechnungGenerator::class);

        // Rechnungsanschrift vollständig, Lieferanschrift ohne Ort: die XRechnung hält an (BR-DE-10).
        $customer = $this->customer();
        $this->ship($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'country' => 'AT']);
        $withoutCity = $this->issued($customer);
        $this->assertContains($message, $generator->preflight($withoutCity)['errors']);
        $this->assertNotContains($message, $generator->preflight($withoutCity, ERechnungProfile::EN16931)['errors']);

        // Rechnungsanschrift ohne Ort, Lieferanschrift vollständig: kein Fehler.
        $other = $this->customer(['address_city' => null, 'address_zip' => null]);
        $this->ship($other, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);
        $this->assertNotContains($message, $generator->preflight($this->issued($other))['errors']);

        // Lieferanschrift ohne Land liegt im Land des Kunden.
        $noCountry = $this->customer();
        $this->ship($noCountry, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz']);
        $address = (new ERechnungParser)->parse($generator->generate($this->issued($noCountry)))->getDeliveryParty()?->getPostalAddress();
        $this->assertSame(['Linz', 'AT'], [$address?->getCity(), $address?->getCountryCode()]);
    }

    public function test_delivery_address_is_frozen_and_corrections_take_it_from_the_original(): void {
        $customer = $this->customer();
        $this->ship($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);
        $original = $this->issued($customer);
        $paid = $this->issued($customer);
        $paid->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => now()])->saveQuietly();
        $this->assertSame('Linz', data_get($original->party_snapshot, 'buyer.shipping_city'));

        // Neue Lieferanschrift am Kunden ändert den ausgestellten Beleg nicht.
        $this->ship($customer, ['street' => 'Neuweg 1', 'zip' => '8010', 'city' => 'Graz', 'country' => 'AT']);
        $this->assertSame('Linz', $this->deliveryCity(Invoice::query()->findOrFail($original->id)));

        // Storno und Gutschrift korrigieren dieselbe Lieferung: Lieferanschrift des Originals.
        $credit = app(InvoiceGenerator::class)->creditNoteFor(Invoice::query()->findOrFail($paid->id), $this->admin->id);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor(Invoice::query()->findOrFail($original->id), null, $this->admin->id);
        foreach ([$credit, $cancellation] as $document) {
            $this->assertSame('Linz', $document->recipient()['shipping_city'], 'Entwurf ' . $document->type);
            $issued = app(InvoiceIssueService::class)->issue($document)->refresh();
            $this->assertSame('Linz', data_get($issued->party_snapshot, 'buyer.shipping_city'), $document->type);
            $this->assertSame('Linz', $this->deliveryCity($issued), $document->type);
        }
        $this->assertSame('Graz', $this->issued($customer->refresh())->recipient()['shipping_city'], 'Neue Rechnung: heutige Lieferanschrift');
    }

    /** Vor MVP-1689 ausgestellt: geliefert wurde an die Rechnungsanschrift, eine spätere Lieferanschrift deutet das nicht um. */
    public function test_snapshot_without_delivery_address_keeps_the_billing_address(): void {
        $customer = $this->customer();
        $invoice = $this->issued($customer);
        $snapshot = $invoice->party_snapshot;
        foreach (['shipping_street', 'shipping_supplement', 'shipping_zip', 'shipping_city', 'shipping_country'] as $key) {
            unset($snapshot['buyer'][$key]);
        }
        Invoice::query()->whereKey($invoice->id)->update(['party_snapshot' => json_encode($snapshot)]);

        $this->ship($customer, ['street' => 'Lagerweg 9', 'zip' => '4020', 'city' => 'Linz', 'country' => 'AT']);

        $reloaded = Invoice::query()->findOrFail($invoice->id);
        $this->assertSame('', $reloaded->recipient()['shipping_city']);
        $this->assertSame('Kundenstadt', $this->deliveryCity($reloaded));
    }

    private function deliveryCity(Invoice $invoice): ?string {
        return (new ERechnungParser)->parse(app(XRechnungGenerator::class)->generate($invoice))->getDeliveryParty()?->getPostalAddress()?->getCity();
    }

    /** @param array{street?: ?string, supplement?: ?string, zip?: ?string, city?: ?string, country?: ?string} $address */
    private function ship(Customer $customer, array $address): void {
        app(ContactDetailsWriter::class)->writeShippingAddress($customer, $address);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel AT',
            'company' => 'Kunde Beispiel GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => 'AT',
            'vat_id' => 'ATU12345675',
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
    }

    private function issued(Customer $customer): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Steuermodul S7',
            'quantity' => '2',
            'unit' => 'Stk',
            'unit_price' => '80.00',
            'tax_category' => 'K',
            'tax_rate' => '0.00',
            'service_date' => '2026-10-15',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $issued = app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
        $this->assertSame(InvoiceStatus::Issued, $issued->status);

        return $issued;
    }

    private function assertKositAccepted(string $xml): void {
        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $result = $kosit->validate($xml);
        $this->assertSame([], array_map('strval', $result->getErrors()));
        $this->assertTrue($result->isAccepted());
    }
}
