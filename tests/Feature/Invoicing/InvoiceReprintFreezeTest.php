<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceReprintFreezeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\XRechnungSyntax;
use App\Mail\InvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, InvoicePdfRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1680: Verkäuferdaten, Bankverbindung und Belegsprache werden beim
 * Ausstellen eingefroren. Nachdruck, XRechnung und ZUGFeRD einer ausgestellten
 * Rechnung bleiben nach Änderungen an Firma, Bank, § 19 oder Kundensprache wie
 * ausgestellt; der Entwurf zeigt den heutigen Stand.
 */
final class InvoiceReprintFreezeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string OLD_IBAN = 'DE89370400440532013000';

    private const string NEW_IBAN = 'DE02120300000000202051';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->settings();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_reprint_after_seller_bank_and_language_changes_stays_as_issued(): void {
        $customer = $this->customer('DE');
        $invoice = $this->issue($this->draft($customer));
        $generator = app(XRechnungGenerator::class);
        $ubl = $generator->generate($invoice, XRechnungSyntax::Ubl);
        $cii = $generator->generate($invoice, XRechnungSyntax::Cii);
        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);
        $this->assertStringContainsString(self::OLD_IBAN, $html);

        $this->settings(seller: 'Neue Firma AG', street: 'Neuer Platz 9', iban: self::NEW_IBAN, bank: 'Neue Bank');
        $customer->update(['document_locale' => 'en']);
        $invoice = Invoice::query()->findOrFail($invoice->id);

        $this->assertSame($ubl, $generator->generate($invoice, XRechnungSyntax::Ubl));
        $this->assertSame($cii, $generator->generate($invoice, XRechnungSyntax::Cii));
        $reprint = app(InvoicePdfRenderer::class)->composedHtml($invoice);
        $this->assertSame($html, $reprint);
        $this->assertStringNotContainsString(self::NEW_IBAN, $reprint);
        $this->assertStringNotContainsString('Neue Bank', $reprint);
        $this->assertStringContainsString('Zahlbar per Überweisung auf folgendes Konto', $reprint);
        $this->assertSame('de', $invoice->documentLocale());
        $this->assertSame('de', (new InvoiceMail($invoice, 'Betreff', '<p>Text</p>', 'Text'))->locale);
    }

    public function test_draft_follows_todays_seller_bank_and_language(): void {
        $customer = $this->customer('DE');
        $draft = $this->draft($customer);

        $this->settings(iban: self::NEW_IBAN, bank: 'Neue Bank');
        $customer->update(['document_locale' => 'en']);
        $draft = Invoice::query()->findOrFail($draft->id);
        $html = app(InvoicePdfRenderer::class)->composedHtml($draft);

        $this->assertStringContainsString(self::NEW_IBAN, $html);
        $this->assertStringContainsString('Neue Bank', $html);
        $this->assertStringContainsString((string) trans('Zahlbar per Überweisung auf folgendes Konto', [], 'en'), $html);
        $this->assertSame('en', $draft->documentLocale());
    }

    /** Die E-Rechnung schreibt ihre Texte in der Belegsprache, gleich in welcher Sprache die Oberfläche läuft. */
    public function test_einvoice_texts_follow_the_frozen_document_language(): void {
        $customer = $this->customer('AT', 'ATU12345675', 'fr');
        $invoice = $this->issue($this->draft($customer));
        $generator = app(XRechnungGenerator::class);

        $french = $generator->generate($invoice, XRechnungSyntax::Ubl);
        $customer->update(['document_locale' => 'it']);
        App::setLocale('en');
        $invoice = Invoice::query()->findOrFail($invoice->id);

        $this->assertSame($french, $generator->generate($invoice, XRechnungSyntax::Ubl));
        $this->assertStringContainsString((string) trans('invoicing.einvoice.exemption_reverse_charge', [], 'fr'), $french);
        $this->assertSame('en', App::getLocale(), 'Die Oberflächensprache bleibt unberührt.');
    }

    public function test_small_business_status_of_the_issue_date_stays_on_the_reprint(): void {
        $this->settings(vatId: '', smallBusiness: true);
        $draft = $this->draft($this->customer('DE'));
        // Ohne den Hinweis im Rechnungstext druckt das PDF die § 19-Zeile selbst.
        $draft->update(['notes' => null]);
        $invoice = $this->issue($draft->refresh());
        $ubl = app(XRechnungGenerator::class)->generate($invoice, XRechnungSyntax::Ubl);

        $this->settings();
        $invoice = Invoice::query()->findOrFail($invoice->id);

        $this->assertStringContainsString('§ 19 UStG', app(InvoicePdfRenderer::class)->composedHtml($invoice));
        $this->assertSame($ubl, app(XRechnungGenerator::class)->generate($invoice, XRechnungSyntax::Ubl));
        $this->assertNull(app(SalesInvoiceAdapter::class)->zeroRatedCategory($this->organization->refresh(), $invoice));
    }

    /** Snapshots vor MVP-1680 führen Bank und Sprache nicht: nur diese Felder kommen aus dem heutigen Stand. */
    public function test_older_snapshot_falls_back_per_field(): void {
        $invoice = $this->issue($this->draft($this->customer('DE')));
        $snapshot = $invoice->party_snapshot;
        unset($snapshot['legal'], $snapshot['locale'], $snapshot['seller']['iban']);
        $invoice->forceFill(['party_snapshot' => $snapshot])->saveQuietly();

        $this->settings(seller: 'Neue Firma AG', iban: self::NEW_IBAN, bank: 'Neue Bank');
        $invoice = Invoice::query()->findOrFail($invoice->id);
        $seller = app(XRechnungGenerator::class)->sellerData($invoice);

        $this->assertSame('Beispieltechnik GmbH', $seller['name']);
        $this->assertSame(self::NEW_IBAN, $seller['iban']);
        $this->assertSame('Neue Bank', $invoice->issuerLegal()['bank_name']);
        $this->assertSame('de', $invoice->documentLocale());
    }

    private function settings(string $seller = 'Beispieltechnik GmbH', string $street = 'Musterstraße 1', string $iban = self::OLD_IBAN, string $bank = 'Beispielbank', string $vatId = 'DE136695976', bool $smallBusiness = false): void {
        $this->organization->update(['settings' => [
            'einvoice' => [
                'seller_name' => $seller,
                'street' => $street,
                'zip' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
                'vat_id' => $vatId,
                'tax_number' => '201/987/65432',
                'contact_name' => 'Max Muster',
                'contact_email' => 'rechnung@beispieltechnik.example',
                'contact_phone' => '+49 30 123456',
                'iban' => $iban,
                'bic' => 'COBADEFFXXX',
                'account_holder' => $seller,
                'small_business' => $smallBusiness ? '1' : '0',
            ],
            'branding' => ['legal' => [
                'iban' => $iban,
                'bic' => 'COBADEFFXXX',
                'bank_name' => $bank,
                'account_holder' => $seller,
            ]],
        ]]);
        $this->organization->refresh();
    }

    private function customer(string $country, ?string $vatId = null, ?string $locale = null): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel ' . $country,
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => $country,
            'vat_id' => $vatId,
            'document_locale' => $locale,
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
    }

    private function draft(Customer $customer): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Montage',
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'service_date' => '2026-10-15',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->refresh();
    }

    private function issue(Invoice $invoice): Invoice {
        return app(InvoiceIssueService::class)->issue($invoice)->refresh();
    }
}
