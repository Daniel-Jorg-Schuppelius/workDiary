<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceChannelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Document, EmailConnection, IncomingEInvoice, Invoice, Supplier, User};
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, XRechnungGenerator};
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 066, Restpaket (MVP-165/166/167/168): Mail-Eingangskanal über
 * dieselbe Verarbeitung wie der Upload (Hash-Dedup kanalübergreifend),
 * Lieferanten-/Bestell-Vorschläge + Abweichungsprüfung beim Empfang,
 * XML-Download des extrahierten Originals und idempotente Übergabe an
 * die führende Buchhaltung.
 */
final class IncomingEInvoiceChannelsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
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
    }

    /** Echte XRechnung über den Ausgangs-Generator (Roundtrip-Fixture). */
    private function sampleXml(string $number = 'ER-2026-0042', string $unitPrice = '100.00'): string {
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
            'issued_on' => '2026-06-01',
            'due_on' => '2026-06-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartungspauschale',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => $unitPrice,
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(XRechnungGenerator::class)->generate($invoice->fresh(['items', 'customer']));
    }

    private function connection(bool $einvoiceIntake = true): EmailConnection {
        return EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnungen',
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'rechnung@example.test',
            'password' => 'geheim',
            'folder' => 'INBOX',
            'active' => true,
            'einvoice_intake' => $einvoiceIntake,
            'created_by' => $this->admin->id,
        ]);
    }

    private function message(string $xml, string $messageId): ParsedMessage {
        return new ParsedMessage(
            messageId: $messageId,
            uid: random_int(1, 99999),
            fromEmail: 'lieferant@example.test',
            fromName: 'Lieferant GmbH',
            subject: 'Ihre Rechnung',
            body: 'Anbei die Rechnung.',
            receivedAt: Carbon::now(),
            attachmentCount: 1,
            attachments: [new MailAttachment('rechnung.xml', 'application/xml', $xml)],
        );
    }

    public function test_mail_channel_stores_incoming_einvoice_with_cross_channel_dedup(): void {
        $xml = $this->sampleXml();
        $connection = $this->connection();
        $intake = app(MailIntakeService::class);

        $result = $intake->intake($this->organization, $connection, $this->message($xml, '<m1@example.test>'));
        $this->assertSame('einvoice', $result);

        $incoming = IncomingEInvoice::query()->firstOrFail();
        $this->assertSame('mail', $incoming->source);
        $this->assertSame(hash('sha256', $xml), $incoming->sha256);
        $this->assertSame('ER-2026-0042', (string) data_get($incoming->summary, 'number'));

        // Kanalübergreifende Dublette: identischer Inhalt (andere Message-ID)
        // erzeugt weder zweites Document noch Inbox-Item.
        $documents = Document::query()->count();
        $result = $intake->intake($this->organization, $connection, $this->message($xml, '<m2@example.test>'));
        $this->assertSame('skipped', $result);
        $this->assertSame(1, IncomingEInvoice::query()->count());
        $this->assertSame($documents, Document::query()->count());
        $this->assertSame(0, \App\Models\IntegrationInboxItem::query()->count());
    }

    public function test_mail_without_einvoice_flag_falls_through_to_inbox(): void {
        $xml = $this->sampleXml();
        $connection = $this->connection(einvoiceIntake: false);

        $result = app(MailIntakeService::class)->intake($this->organization, $connection, $this->message($xml, '<m3@example.test>'));

        $this->assertSame('created', $result);
        $this->assertSame(0, IncomingEInvoice::query()->count());
    }

    public function test_suggestions_and_deviations_are_computed_on_receipt(): void {
        // Lieferant mit passender USt-IdNr. (Verkäufer der Beispielrechnung).
        Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Lieferant GmbH',
            'vat_id' => 'DE123456789',
        ]);

        $service = app(IncomingEInvoiceService::class);
        $first = $service->storeIncoming($this->admin, $this->sampleXml(), 'application/xml');
        $this->assertSame('created', $first['status']);

        $suggestions = (array) data_get($first['incoming']->summary, 'suggestions.suppliers');
        $this->assertNotEmpty($suggestions, 'Lieferanten-Vorschlag über USt-IdNr. erwartet.');
        $this->assertSame([], (array) data_get($first['incoming']->summary, 'deviations'));

        // Gleiche Rechnungsnummer, anderer Inhalt → Abweichung wird eskaliert.
        // (Lokal sind Nummern je Org unique — daher Nummer im XML angleichen.)
        $xml = str_replace('ER-2026-0099', 'ER-2026-0042', $this->sampleXml('ER-2026-0099', '150.00'));
        $second = $service->storeIncoming($this->admin, $xml, 'application/xml');
        $this->assertSame('created', $second['status']);
        $deviations = (array) data_get($second['incoming']->summary, 'deviations');
        $this->assertNotEmpty($deviations, 'Doppelte Rechnungsnummer muss als Abweichung erscheinen.');
    }

    public function test_xml_download_and_idempotent_transfer(): void {
        $xml = $this->sampleXml();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('rechnung.xml', $xml),
        ])->assertRedirect();

        $incoming = IncomingEInvoice::query()->firstOrFail();
        $document = Document::query()->findOrFail($incoming->document_id);

        // XML-Download liefert das extrahierte Original.
        $response = $this->actingAs($this->admin)->get(route('finance.incoming-invoices.xml', $document));
        $response->assertOk();
        $this->assertSame(hash('sha256', $xml), hash('sha256', (string) $response->getContent()));

        // Übergabe erst nach fachlicher Freigabe.
        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.transfer', $incoming))
            ->assertSessionHas('error');
        $this->assertNull($incoming->fresh()->transferred_at);

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'approved']);
        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.transfer', $incoming))
            ->assertSessionHas('success');
        $transferredAt = $incoming->fresh()->transferred_at;
        $this->assertNotNull($transferredAt);

        // Idempotent: zweiter Aufruf ändert den Nachweis nicht.
        $this->travel(1)->hours();
        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.transfer', $incoming))
            ->assertSessionHas('success');
        $this->assertTrue($transferredAt->equalTo($incoming->fresh()->transferred_at));
    }
}
