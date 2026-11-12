<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssuerBlocksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderPreflightService, RenderProfileService};
use App\Services\Invoicing\{InvoicePdfRenderer, QuotePdfRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-1609: Firmen-, Steuer-, Ansprechpartner-, Einleitungs-, Schluss- und
 * Vertraulichkeitsblock wirken im echten Beleg nach ihrem Zustand; „dynamisch"
 * erfüllt den Pflichtblock nur mit gepflegten Stammdaten; ältere ausgestellte
 * Rechnungen bleiben unverändert.
 */
class IssuerBlocksTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->withIssuerIdentity()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id, 'name' => 'Vera Vertrieb', 'phone' => '+49 30 123456']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function invoice(): Invoice {
        return Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'sales_user_id' => $this->admin->id,
            'number' => 'R2026-' . fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
        ]);
    }

    /**
     * @param array<string, string> $states Block → Zustand
     * @param array<string, string|null> $texts
     * @return array<string, mixed>
     */
    private function payload(array $states, array $texts = []): array {
        $rules = [];
        foreach ($states as $block => $state) {
            $rules[$block] = ['state' => $state, 'confirmed' => true];
        }

        return ['block_rules' => $rules, 'content_texts' => $texts, 'generator_version' => DocumentDesignRenderer::GENERATOR_VERSION];
    }

    private function invoiceHtml(Invoice $invoice, ?array $payload = null): string {
        return view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice, $payload))->render();
    }

    public function test_dynamic_blocks_print_issuer_tax_and_contact_data(): void {
        $html = $this->invoiceHtml($this->invoice());

        $this->assertStringContainsString('data-block="company_identity"', $html);
        $this->assertStringContainsString('Musterstraße 1', $html);
        $this->assertStringContainsString('12345 Musterstadt', $html);
        $this->assertStringContainsString('data-block="tax_identity"', $html);
        $this->assertStringContainsString('DE123456789', $html);
        $this->assertStringContainsString('data-block="contact_person"', $html);
        $this->assertStringContainsString('Vera Vertrieb', $html);
        // Vertraulichkeit ist ohne Profil „nicht anwendbar".
        $this->assertStringNotContainsString('data-block="confidentiality"', $html);
    }

    public function test_letterhead_and_not_applicable_states_omit_the_blocks(): void {
        $html = $this->invoiceHtml($this->invoice(), $this->payload([
            'company_identity' => 'provided_by_letterhead',
            'tax_identity' => 'provided_by_letterhead',
            'contact_person' => 'not_applicable',
            'intro_text' => 'not_applicable',
            'closing_text' => 'not_applicable',
        ], ['header_text' => 'Kopftext XY', 'footer_text' => 'Fußtext XY']));

        $this->assertStringNotContainsString('data-block="company_identity"', $html);
        $this->assertStringNotContainsString('data-block="tax_identity"', $html);
        $this->assertStringNotContainsString('data-block="contact_person"', $html);
        $this->assertStringNotContainsString('Kopftext XY', $html, 'Einleitung „nicht anwendbar" unterdrückt den Kopftext.');
        $this->assertStringNotContainsString('Fußtext XY', $html, 'Schluss „nicht anwendbar" unterdrückt den Fußtext.');
    }

    public function test_intro_and_closing_print_the_design_texts_once(): void {
        $html = $this->invoiceHtml($this->invoice(), $this->payload([], ['header_text' => 'Kopftext XY', 'footer_text' => 'Fußtext XY']));

        $this->assertSame(1, substr_count($html, 'Kopftext XY'));
        $this->assertSame(1, substr_count($html, 'Fußtext XY'));
    }

    public function test_confidentiality_prints_custom_or_default_notice(): void {
        $custom = $this->invoiceHtml($this->invoice(), $this->payload(['confidentiality' => 'dynamic'], ['confidentiality_text' => 'Nur für die Geschäftsleitung']));
        $this->assertStringContainsString('Nur für die Geschäftsleitung', $custom);

        $default = $this->invoiceHtml($this->invoice(), $this->payload(['confidentiality' => 'dynamic']));
        $this->assertStringContainsString('Vertraulich — nur für den benannten Empfänger bestimmt.', $default);
    }

    public function test_issued_invoice_keeps_frozen_issuer_and_contact(): void {
        $invoice = $this->invoice();
        $invoice->freezeParties();
        $invoice->save();

        $settings = (array) $this->org->settings;
        $settings['einvoice']['street'] = 'Neue Straße 99';
        $this->org->forceFill(['settings' => $settings])->save();
        $this->admin->forceFill(['name' => 'Neuer Name'])->save();

        $html = $this->invoiceHtml($invoice->refresh());
        $this->assertStringContainsString('Musterstraße 1', $html);
        $this->assertStringNotContainsString('Neue Straße 99', $html);
        $this->assertStringContainsString('Vera Vertrieb', $html);
    }

    public function test_invoices_issued_before_the_blocks_stay_unchanged(): void {
        $invoice = $this->invoice();
        $invoice->freezeParties();
        $invoice->save();
        // Bestandsbeleg: Snapshot des früheren Generators.
        DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_id', $invoice->id)
            ->update(['generator_version' => 'document-design/1.0.0']);

        $html = $this->invoiceHtml($invoice->refresh());
        $this->assertStringNotContainsString('data-block="company_identity"', $html);
        $this->assertStringNotContainsString('data-block="tax_identity"', $html);
        $this->assertStringNotContainsString('data-block="contact_person"', $html);
    }

    public function test_quote_names_its_creator_as_contact(): void {
        $quote = Quote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'created_by' => $this->admin->id]);

        $html = view('quotes.pdf', app(QuotePdfRenderer::class)->viewData($quote))->render();
        $this->assertStringContainsString('data-block="contact_person"', $html);
        $this->assertStringContainsString('Vera Vertrieb', $html);
        $this->assertStringContainsString('data-block="company_identity"', $html);
    }

    /** MVP-1624: Angebot (Versand) und Auftragsbestätigung (Bestätigung) frieren Aussteller und Ansprechpartner ein wie die Rechnung. */
    public function test_sent_quote_and_confirmed_order_confirmation_keep_frozen_issuer_and_contact(): void {
        Storage::fake('documents');
        $quote = Quote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'created_by' => $this->admin->id, 'status' => \App\Enums\Sales\QuoteStatus::Approved]);
        \App\Models\Sales\QuoteItem::factory()->create(['organization_id' => $this->org->id, 'quote_id' => $quote->id]);
        $quote = app(\App\Services\Invoicing\QuoteService::class)->send($quote, $this->admin)['quote'];
        $this->assertSame('Musterstraße 1', $quote->issuer_snapshot['issuer']['street'] ?? null);

        $confirmation = \App\Models\Sales\OrderConfirmation::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'created_by' => $this->admin->id]);
        \App\Models\Sales\OrderConfirmationItem::factory()->create(['organization_id' => $this->org->id, 'order_confirmation_id' => $confirmation->id]);
        $confirmation = app(\App\Services\Sales\OrderConfirmationService::class)->confirm($confirmation, $this->admin);
        $this->assertSame('Vera Vertrieb', $confirmation->issuer_snapshot['contact']['name'] ?? null);

        $settings = (array) $this->org->settings;
        $settings['einvoice']['street'] = 'Neue Straße 99';
        $this->org->forceFill(['settings' => $settings])->save();
        $this->admin->forceFill(['name' => 'Neuer Name'])->save();

        foreach ([
            view('quotes.pdf', app(QuotePdfRenderer::class)->viewData($quote->refresh()))->render(),
            view('quotes.order-confirmation-pdf', app(\App\Services\Invoicing\OrderConfirmationPdfRenderer::class)->viewData($confirmation->refresh()))->render(),
        ] as $html) {
            $this->assertStringContainsString('Musterstraße 1', $html);
            $this->assertStringNotContainsString('Neue Straße 99', $html);
            $this->assertStringContainsString('Vera Vertrieb', $html);
            $this->assertStringNotContainsString('Neuer Name', $html);
        }
    }

    /** MVP-1624: Lieferschein und Protokoll drucken die Firmenangaben nach Blockzustand; der Preflight prüft Bestellung, Lieferschein und Protokoll wie Vertriebsbelege. */
    public function test_non_sales_documents_print_and_preflight_the_company_identity(): void {
        $captured = [];
        \Illuminate\Support\Facades\View::composer(['pdf.delivery-note', 'protocols.pdf'], function (\Illuminate\View\View $view) use (&$captured): void {
            $captured[$view->name()] = $view->getData();
        });
        $note = \App\Models\Billing\DeliveryNote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        app(\App\Services\Billing\DeliveryNotePdfRenderer::class)->render($note);
        $protocol = \App\Models\Protocol\Protocol::factory()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->admin->id]);
        app(\App\Services\Protocol\ProtocolPdfRenderer::class)->content($protocol);

        foreach (['pdf.delivery-note', 'protocols.pdf'] as $name) {
            $html = view($name, $captured[$name])->render();
            $this->assertStringContainsString('data-block="company_identity"', $html, $name);
            $this->assertStringContainsString('Musterstraße 1', $html, $name);
        }

        $service = app(RenderProfileService::class);
        $bare = Organization::factory()->create();
        $bareAdmin = User::factory()->admin()->create(['organization_id' => $bare->id]);
        app()->instance('currentOrganization', $bare);
        foreach ([RenderDocumentKind::PurchaseOrder, RenderDocumentKind::DeliveryNote, RenderDocumentKind::Protocol] as $kind) {
            $profile = $service->createProfile($bare, 'Profil ' . $kind->value, [$kind->value], false, $bareAdmin);
            $result = app(RenderPreflightService::class)->check($profile->versions()->firstOrFail());
            $this->assertContains('company_identity', array_column(array_filter($result->errors, fn (array $e): bool => $e['code'] === 'mandatory_data_missing'), 'block'), $kind->value);
        }
    }

    public function test_preflight_counts_dynamic_only_with_master_data(): void {
        $service = app(RenderProfileService::class);
        $bare = Organization::factory()->create();
        $bareAdmin = User::factory()->admin()->create(['organization_id' => $bare->id]);
        app()->instance('currentOrganization', $bare);

        // An Rechnungen gebunden, ohne Anschrift und Steuerangabe → Fehler.
        $bound = $service->createProfile($bare, 'Rechnungen', [RenderDocumentKind::Invoice->value], false, $bareAdmin);
        $result = app(RenderPreflightService::class)->check($bound->versions()->firstOrFail());
        $this->assertFalse($result->ok());
        $this->assertEqualsCanonicalizing(['company_identity', 'tax_identity'], array_column(array_filter($result->errors, fn (array $e): bool => $e['code'] === 'mandatory_data_missing'), 'block'));

        // Basisdesign deckt Rechnungen nur als Rückfall → Warnung statt Sperre.
        $base = $service->createProfile($bare, 'CI', [], true, $bareAdmin);
        $baseResult = app(RenderPreflightService::class)->check($base->versions()->firstOrFail(), [RenderDocumentKind::Invoice->value, RenderDocumentKind::Report->value]);
        $this->assertTrue($baseResult->ok());
        $this->assertContains('mandatory_data_missing', array_column($baseResult->warnings, 'code'));

        // Berichte drucken keine Ausstellerblöcke → kein Datenbefund.
        $report = $service->createProfile($bare, 'Berichte', [RenderDocumentKind::Report->value], false, $bareAdmin);
        $reportResult = app(RenderPreflightService::class)->check($report->versions()->firstOrFail());
        $this->assertNotContains('mandatory_data_missing', array_merge(array_column($reportResult->errors, 'code'), array_column($reportResult->warnings, 'code')));

        // Mit gepflegten Stammdaten ist „dynamisch" erfüllt.
        app()->instance('currentOrganization', $this->org);
        $withData = $service->createProfile($this->org, 'Rechnungen', [RenderDocumentKind::Invoice->value], false, $this->admin);
        $this->assertTrue(app(RenderPreflightService::class)->check($withData->versions()->firstOrFail())->ok());
    }
}
