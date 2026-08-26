<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolInboundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Peppol;

use App\Models\{Customer, IncomingEInvoice, Invoice, Organization, User};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Plugins\PluginManager;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Peppol\PeppolInboundService;
use ERechnungToolkit\Peppol\{ParticipantId, Sbdh};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Peppol-Eingang (Feature 066, MVP-734).
 *
 * Der Abhol-Endpunkt ist nur eine weitere Herkunft der bestehenden
 * Rechnungseingangs-Strecke — geprüft wird deshalb vor allem das
 * Quittungsverhalten: übernommen ⇒ quittiert, unlesbar ⇒ liegen lassen.
 */
class PeppolInboundTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const AP_BASE = 'https://ap.example.test/v1';

    private const OWN = '9930:DE123456789';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Lieferant GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@lieferant.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Lieferant GmbH',
            'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->enablePluginFor($this->organization, PeppolAccessPointPlugin::ID, [
            'base_url' => self::AP_BASE,
            'api_key' => 'geheim',
            'sender_participant_id' => self::OWN,
        ]);
        app(PluginManager::class)->flushRuntimeCaches();
    }

    /** Eine echte XRechnung über den Ausgangs-Generator (Roundtrip-Fixture). */
    private function ubl(string $number = 'ER-2027-0001'): string {
        $customer = Customer::query()->firstOrCreate([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
        ], [
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => '2027-02-01',
            'due_on' => '2027-02-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartungspauschale',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(XRechnungGenerator::class)->generate($invoice->fresh(['items', 'customer']));
    }

    /**
     * SBDH-Umschlag mit dem UBL als Nutzlast. `$payload` überschreibt die
     * Nutzlast, ohne den Kopf zu verändern — genau der Fall „Umschlag in
     * Ordnung, Inhalt unbrauchbar".
     */
    private function envelope(?string $payload = null, string $number = 'ER-2027-0001'): string {
        $ubl = $this->ubl($number);

        return Sbdh::forUbl(
            $ubl,
            new ParticipantId('9930:DE555000111'),
            new ParticipantId(self::OWN),
            'DE',
        )->envelope($payload ?? $ubl);
    }

    /** @param list<array<string, string>> $documents */
    private function fakeHttp(array $documents, int $ackStatus = 204): FakePluginHttp {
        return FakePluginHttp::fake([
            self::AP_BASE . '/inbox/*/acknowledge*' => FakePluginHttp::response([], $ackStatus),
            self::AP_BASE . '/inbox*' => FakePluginHttp::response(['documents' => $documents]),
        ]);
    }

    public function test_inbound_document_lands_in_the_invoice_intake_and_is_acknowledged(): void {
        $fake = $this->fakeHttp([['messageId' => 'IN-1', 'document' => $this->envelope()]]);

        $counters = app(PeppolInboundService::class)->poll($this->organization);

        $this->assertSame(1, $counters['fetched']);
        $this->assertSame(1, $counters['imported']);
        $this->assertSame(1, $counters['acknowledged']);

        $incoming = IncomingEInvoice::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('peppol', $incoming->source);
        $this->assertSame((int) $this->organization->id, (int) $incoming->organization_id);
        $this->assertNotNull($incoming->document_id, 'Der Eingang gehört als Original ins DMS.');

        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/inbox/IN-1/acknowledge'));
    }

    public function test_duplicate_is_acknowledged_but_not_stored_twice(): void {
        $envelope = $this->envelope();
        $this->fakeHttp([['messageId' => 'IN-1', 'document' => $envelope]]);
        app(PeppolInboundService::class)->poll($this->organization);

        // Derselbe Beleg kommt erneut — der Hash-Dedup der Eingangsstrecke greift.
        $this->fakeHttp([['messageId' => 'IN-2', 'document' => $envelope]]);
        $counters = app(PeppolInboundService::class)->poll($this->organization);

        $this->assertSame(1, $counters['duplicates']);
        $this->assertSame(1, $counters['acknowledged'], 'Eine Dublette muss quittiert werden, sonst kommt sie ewig wieder.');
        $this->assertSame(1, IncomingEInvoice::query()->withoutGlobalScopes()->count());
    }

    public function test_unreadable_payload_is_not_acknowledged(): void {
        $fake = $this->fakeHttp([['messageId' => 'IN-BAD', 'document' => $this->envelope('<Nutzlast>kein Rechnungsformat</Nutzlast>')]]);

        $counters = app(PeppolInboundService::class)->poll($this->organization);

        $this->assertSame(1, $counters['unreadable']);
        $this->assertSame(0, $counters['acknowledged']);
        $this->assertSame(0, IncomingEInvoice::query()->withoutGlobalScopes()->count());
        // Nicht quittiert heißt: der Beleg bleibt beim Provider liegen und
        // kommt wieder — lieber ein zweiter Eingang als ein verlorener Beleg.
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), 'acknowledge'));
    }

    public function test_organization_without_credentials_does_nothing(): void {
        $other = Organization::factory()->create();
        $fake = $this->fakeHttp([['messageId' => 'IN-1', 'document' => $this->envelope()]]);

        $counters = app(PeppolInboundService::class)->poll($other);

        $this->assertSame(0, $counters['fetched']);
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/inbox'));
    }

    public function test_console_command_runs_only_for_organizations_with_the_plugin(): void {
        $this->fakeHttp([['messageId' => 'IN-1', 'document' => $this->envelope()]]);
        Organization::factory()->create();

        $this->artisan('peppol:receive')->assertExitCode(0);

        $this->assertSame(1, IncomingEInvoice::query()->withoutGlobalScopes()->count());
    }
}
