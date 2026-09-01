<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLocaleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Mail\{DunningMail, InvoiceMail};
use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Support\DocumentLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Belegsprache je Kunde (Feature 034, MVP-721; Vollscan H19): Kunde →
 * Organisation → Anzeige-Sprache. Nur Darstellung — Snapshots, Hash-Ketten
 * und tax_context bleiben unberührt.
 */
class DocumentLocaleTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create(['locale' => 'de']);
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        App::setLocale('de');
    }

    private function customer(?string $documentLocale): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->org->id,
            'document_locale' => $documentLocale,
        ]);
    }

    private function invoiceFor(Customer $customer): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'R2030-0007',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => '2030-03-01',
            'due_on' => '2030-03-15',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer', 'organization']);
    }

    public function test_resolution_customer_then_organization_then_display_locale(): void {
        $this->assertSame('fr', DocumentLocale::for($this->customer('fr'), $this->org));
        $this->assertSame('de', DocumentLocale::for($this->customer(null), $this->org));

        $this->org->forceFill(['locale' => 'it'])->save();
        $this->assertSame('it', DocumentLocale::for($this->customer(null), $this->org->fresh()));

        // Unbekannte Codes werden ignoriert, nicht durchgereicht.
        $this->assertSame('it', DocumentLocale::for($this->customer(null)->forceFill(['document_locale' => 'xx']), $this->org->fresh()));

        // organizations.locale ist NOT NULL — ein nicht aktivierter Code
        // (z. B. nach Änderung der ENV-Whitelist) fällt auf die Anzeige-Sprache.
        $this->org->forceFill(['locale' => 'xx'])->save();
        App::setLocale('es');
        $this->assertSame('es', DocumentLocale::for($this->customer(null), $this->org->fresh()));
    }

    public function test_invoice_html_renders_in_the_customer_document_locale_and_restores_the_locale(): void {
        $invoice = $this->invoiceFor($this->customer('fr'));

        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);

        $this->assertStringContainsString('Date:', $html, 'Rechnungsdatum-Label in Französisch.');
        $this->assertStringNotContainsString('Datum:', $html);
        $this->assertSame('de', App::getLocale(), 'Die Anzeige-Sprache bleibt nach dem Rendern unverändert.');
    }

    public function test_invoice_html_falls_back_to_the_organization_locale(): void {
        $this->org->forceFill(['locale' => 'it'])->save();
        $invoice = $this->invoiceFor($this->customer(null));

        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);

        $this->assertStringContainsString('Data:', $html);
        $this->assertStringNotContainsString('Datum:', $html);
    }

    /**
     * Das Zahlenformat folgt der Belegsprache (MVP-726, Vollscan H19).
     *
     * Bis hierher war die Sprache einstellbar, die Zahl blieb deutsch: eine
     * franzoesische Rechnung wies `1.234,56` aus, wo der Empfaenger
     * `1 234,56` erwartet — ausserhalb des deutschen Sprachraums liest sich
     * `1.234` als Bruchteil.
     */
    public function test_amounts_follow_the_document_locale(): void {
        $invoice = $this->invoiceFor($this->customer('fr'));
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Grossposten',
            'quantity' => '1.000',
            'unit_price' => '1234.5600',
            'tax_rate' => '19.00',
            'position' => 2,
        ]);
        $invoice->load('items');

        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);

        // Geschuetztes Leerzeichen als Tausendertrennung, Komma als Dezimalzeichen.
        $this->assertStringContainsString("1\u{00A0}234,56", $html);
        $this->assertStringNotContainsString('1.234,56', $html);
    }

    /** Deutsche Belege bleiben unveraendert — das ist der Regelfall. */
    public function test_german_documents_keep_the_german_format(): void {
        $invoice = $this->invoiceFor($this->customer('de'));
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Grossposten',
            'quantity' => '1.000',
            'unit_price' => '1234.5600',
            'tax_rate' => '19.00',
            'position' => 2,
        ]);
        $invoice->load('items');

        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);

        $this->assertStringContainsString('1.234,56', $html);
    }

    public function test_dunning_mail_subject_uses_the_customer_locale(): void {
        $invoice = $this->invoiceFor($this->customer('fr'));

        $mail = new DunningMail($invoice, 1);

        $this->assertSame('fr', $mail->locale);
        $subject = $mail->withLocale($mail->locale, static fn (): string => $mail->subjectLine());
        $this->assertSame('Rappel de paiement pour la facture R2030-0007', $subject);
        $this->assertSame('de', App::getLocale());
    }

    public function test_invoice_mail_carries_the_document_locale(): void {
        $invoice = $this->invoiceFor($this->customer('es'));

        $mail = new InvoiceMail($invoice, 'Betreff', '<p>x</p>', 'x');

        $this->assertSame('es', $mail->locale);
    }

    public function test_customer_form_stores_the_document_locale_via_sqid_route(): void {
        $customer = $this->customer(null);

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'currency' => 'EUR',
            'document_locale' => 'fr',
        ])->assertSessionHasNoErrors();
        $this->assertSame('fr', $customer->fresh()->document_locale);

        // Leer = wie Organisation.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'currency' => 'EUR',
            'document_locale' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->document_locale);

        // Nur aktivierte App-Sprachen.
        $this->actingAs($this->admin)->from(route('customers.index'))->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'currency' => 'EUR',
            'document_locale' => 'xx',
        ])->assertSessionHasErrors('document_locale');
        $this->assertNull($customer->fresh()->document_locale);
    }

    public function test_customer_form_offers_the_enabled_locales_and_organization_default(): void {
        $customer = $this->customer('fr');

        $response = $this->actingAs($this->admin)->get(route('customers.edit', $customer) . '?dialog=1');

        $response->assertOk()
            ->assertSee('name="document_locale"', false)
            ->assertSee('Wie Organisation')
            ->assertSee('<option value="fr" selected', false);
    }
}
