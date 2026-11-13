<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungSellerRegisterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\XRechnungSyntax;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\KositValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1695: Die Registerangabe der Organisation (`branding.legal.register`)
 * steht als BT-30 in der E-Rechnung — eingefroren beim Ausstellen, mit
 * feldweisem Rückfall für ältere Snapshots wie bei MVP-1680.
 */
final class XRechnungSellerRegisterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string REGISTER = 'HRB 12345, Amtsgericht Musterstadt';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->settings(self::REGISTER);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_register_goes_to_bt30_in_ubl_and_cii(): void {
        $invoice = $this->issued();
        $this->assertSame(self::REGISTER, data_get($invoice->party_snapshot, 'legal.register'));

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = app(XRechnungGenerator::class)->generate($invoice, $syntax);

            $this->assertSame(self::REGISTER, (new ERechnungParser)->parse($xml)->getSeller()->getLegalRegistrationId(), $syntax->value);
            // BT-30 am Verkäufer, nicht BT-33 (UBL CompanyLegalForm, CII Description).
            $this->assertSame([self::REGISTER], $this->nodes($xml, $syntax === XRechnungSyntax::Ubl
                ? '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID'
                : '//ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID'), $syntax->value);
            $this->assertSame([], $this->nodes($xml, $syntax === XRechnungSyntax::Ubl
                ? '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyLegalForm'
                : '//ram:SellerTradeParty/ram:Description'), $syntax->value);
            $this->assertKositAccepted($xml);
        }
    }

    /** Gegenprobe: ohne Registerangabe entfällt BT-30. */
    public function test_without_register_there_is_no_bt30(): void {
        $this->settings(null);
        $invoice = $this->issued();

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = app(XRechnungGenerator::class)->generate($invoice, $syntax);
            $this->assertNull((new ERechnungParser)->parse($xml)->getSeller()->getLegalRegistrationId(), $syntax->value);
            $this->assertKositAccepted($xml);
        }
    }

    public function test_register_is_frozen_with_field_wise_fallback_for_older_snapshots(): void {
        $invoice = $this->issued();
        $this->settings('HRB 99999, Amtsgericht Neustadt');

        $this->assertSame(self::REGISTER, $this->register(Invoice::query()->findOrFail($invoice->id)), 'eingefroren');

        // Snapshot vor MVP-1680 ohne `legal`: die Ausstellerangaben (MVP-1609) tragen die gedruckte Registerangabe.
        $snapshot = $invoice->party_snapshot;
        unset($snapshot['legal']);
        $snapshot['issuer']['register'] = self::REGISTER;
        Invoice::query()->whereKey($invoice->id)->update(['party_snapshot' => json_encode($snapshot)]);
        $this->assertSame(self::REGISTER, $this->register(Invoice::query()->findOrFail($invoice->id)), 'aus den Ausstellerangaben');

        // Ohne beide: der heutige Stand.
        unset($snapshot['issuer']);
        Invoice::query()->whereKey($invoice->id)->update(['party_snapshot' => json_encode($snapshot)]);
        $this->assertSame('HRB 99999, Amtsgericht Neustadt', $this->register(Invoice::query()->findOrFail($invoice->id)), 'heutiger Stand');
    }

    /** @return list<string> */
    private function nodes(string $xml, string $path): array {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $values = [];
        foreach ($xpath->query($path) ?: [] as $node) {
            $values[] = trim((string) $node->nodeValue);
        }

        return $values;
    }

    private function register(Invoice $invoice): ?string {
        return (new ERechnungParser)->parse(app(XRechnungGenerator::class)->generate($invoice))->getSeller()->getLegalRegistrationId();
    }

    private function settings(?string $register): void {
        $this->organization->update(['settings' => [
            'einvoice' => [
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
            ],
            'branding' => ['legal' => ['register' => $register]],
        ]]);
        $this->organization->refresh();
    }

    private function issued(): Invoice {
        $customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel',
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => 'DE',
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartung',
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'service_date' => '2026-10-15',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
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
