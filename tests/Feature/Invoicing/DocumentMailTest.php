<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMailTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Mail\DocumentMail;
use App\Models\{Article, ArticleVariant, AuditLog, Customer, DocumentDispatch, InvoiceMailTemplate, PurchaseOrder, Quote, StockDelivery, Supplier, User, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Invoicing\QuoteService;
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService};
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 128 (MVP-692): Generischer Belegversand — Angebot, AB, Bestellung
 * und Lieferschein per E-Mail mit PDF-Anhang, Vorlage je Belegart,
 * Zustellnachweis in document_dispatches, Audit `{kind}.mailed`.
 */
class DocumentMailTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization([
            'settings' => [
                'branding' => [
                    'legal' => [
                        'iban' => 'DE02120300000000202051',
                        'bank_name' => 'Testbank',
                        'account_holder' => 'ACME GmbH',
                    ],
                ],
            ],
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'kunde@example.test',
        ]);
        Mail::fake();
    }

    private function approvedQuote(): Quote {
        $service = app(QuoteService::class);
        $quote = $service->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => '19.00'],
        ], $this->admin);

        return $service->approve($quote, $this->admin);
    }

    private function acceptedQuote(): Quote {
        $service = app(QuoteService::class);
        $quote = $this->approvedQuote();
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->admin);
        $item = $quote->items()->orderBy('position')->firstOrFail();

        return $service->accept($quote, [(int) $item->id], $token);
    }

    private function purchaseOrder(): PurchaseOrder {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'lieferant@example.test',
        ]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        return app(PurchaseOrderService::class)->createDraft($this->organization, $supplier, $warehouse);
    }

    private function stockDelivery(): StockDelivery {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $article, $variant, '5', 'Stk',
            ['warehouse_id' => $warehouse->id],
        );
        app(InventoryLedger::class)->receipt($variant, $warehouse, '10');

        return app(DeliveryService::class)->deliver($variant, $warehouse, '3', $order, $this->customer);
    }

    // ── Versand je Belegart ──────────────────────────────────────────────

    public function test_quote_mail_queues_pdf_writes_dispatch_and_audit(): void {
        $quote = $this->approvedQuote();

        $this->actingAs($this->admin)->post(route('quotes.mail', $quote), [
            'to' => ['kunde@example.test'],
        ])->assertRedirect(route('quotes.show', $quote));

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) use ($quote): bool {
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());

            return $mail->hasTo('kunde@example.test')
                && str_contains($mail->renderedSubject, $quote->number)
                && $names === [sprintf('angebot-%s-v%d.pdf', $quote->number, $quote->version)];
        });

        $dispatch = DocumentDispatch::query()->forDocument(RenderDocumentKind::Quote, (int) $quote->id)->firstOrFail();
        $this->assertSame(DocumentDispatch::CHANNEL_EMAIL, $dispatch->channel);
        $this->assertSame('queued', $dispatch->status);
        $this->assertSame('kunde@example.test', $dispatch->recipient);
        $this->assertNotNull($dispatch->sha256, 'PDF-Hash wird beim Anhang-Rendern festgehalten.');

        $this->assertTrue(AuditLog::query()
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $quote->id)
            ->where('event', 'quote.mailed')
            ->exists());
    }

    public function test_quote_mail_rejects_drafts(): void {
        $quote = app(QuoteService::class)->create([
            'customer_id' => $this->customer->id,
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => '19.00'],
        ], $this->admin);

        $this->actingAs($this->admin)->post(route('quotes.mail', $quote), [
            'to' => ['kunde@example.test'],
        ])->assertStatus(422);
        Mail::assertNothingQueued();
    }

    public function test_order_confirmation_mail_requires_acceptance(): void {
        $quote = $this->approvedQuote();

        $this->actingAs($this->admin)->post(route('quotes.order-confirmation.mail', $quote), [
            'to' => ['kunde@example.test'],
        ])->assertStatus(422);

        $quote = $this->acceptedQuote();
        $this->actingAs($this->admin)->post(route('quotes.order-confirmation.mail', $quote), [
            'to' => ['kunde@example.test'],
        ])->assertRedirect(route('quotes.show', $quote));

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) use ($quote): bool {
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());

            return $names === [sprintf('auftragsbestaetigung-%s.pdf', $quote->number)];
        });
        $this->assertSame(1, DocumentDispatch::query()->forDocument(RenderDocumentKind::OrderConfirmation, (int) $quote->id)->count());
        $this->assertTrue(AuditLog::query()->where('event', 'order_confirmation.mailed')->exists());
    }

    public function test_purchase_order_mail_prefills_supplier_and_sends(): void {
        $po = $this->purchaseOrder();

        // Formular: Empfänger-Vorbelegung aus dem Lieferanten.
        $this->actingAs($this->admin)->get(route('purchase-orders.mail.form', $po))
            ->assertOk()
            ->assertSee('lieferant@example.test');

        $this->actingAs($this->admin)->post(route('purchase-orders.mail', $po), [
            'to' => ['lieferant@example.test'],
        ])->assertRedirect(route('purchase-orders.show', $po));

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) use ($po): bool {
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());

            return str_contains($mail->renderedSubject, $po->number)
                && $names === ['Bestellung_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $po->number) . '.pdf'];
        });
        $this->assertSame(1, DocumentDispatch::query()->forDocument(RenderDocumentKind::PurchaseOrder, (int) $po->id)->count());
        $this->assertTrue(AuditLog::query()->where('event', 'purchase_order.mailed')->exists());
    }

    public function test_delivery_note_mail_sends_and_logs(): void {
        $delivery = $this->stockDelivery();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.deliveries.mail', [$delivery->order, $delivery]), [
            'to' => ['kunde@example.test'],
        ])->assertRedirect();

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail): bool {
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());

            return count($names) === 1 && str_starts_with($names[0], 'LS-') && str_ends_with($names[0], '.pdf');
        });
        $this->assertSame(1, DocumentDispatch::query()->forDocument(RenderDocumentKind::DeliveryNote, (int) $delivery->id)->count());
        $this->assertTrue(AuditLog::query()->where('event', 'delivery_note.mailed')->exists());
    }

    // ── Vorlagen-Auflösung je Belegart ───────────────────────────────────

    public function test_template_resolution_prefers_kind_default_and_falls_back(): void {
        // Nur eine RECHNUNGS-Default-Vorlage vorhanden → Angebot nutzt den
        // eingebauten Fallback der Belegart, nie die Rechnungs-Vorlage.
        InvoiceMailTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnung Standard',
            'document_kind' => 'invoice',
            'is_default' => true,
            'subject' => 'RECHNUNG {{invoice_number}}',
            'body_html' => '<p>Rechnung</p>',
            'body_text' => 'Rechnung',
        ]);

        $quote = $this->approvedQuote();
        $this->actingAs($this->admin)->post(route('quotes.mail', $quote), [
            'to' => ['kunde@example.test'],
        ])->assertRedirect();

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) use ($quote): bool {
            return ! str_contains($mail->renderedSubject, 'RECHNUNG')
                && str_contains($mail->renderedSubject, $quote->number);
        });

        // Mit Angebots-Default-Vorlage wird DIESE gezogen.
        InvoiceMailTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Angebot Standard',
            'document_kind' => 'quote',
            'is_default' => true,
            'subject' => 'ANGEBOT {{document_number}} bis {{valid_until}}',
            'body_html' => '<p>{{custom_text}}</p>',
            'body_text' => '{{custom_text}}',
        ]);

        $this->actingAs($this->admin)->post(route('quotes.mail', $quote), [
            'to' => ['kunde@example.test'],
            'custom_text' => 'Begleittext dazu.',
        ])->assertRedirect();

        Mail::assertQueued(DocumentMail::class, function (DocumentMail $mail) use ($quote): bool {
            return $mail->renderedSubject === 'ANGEBOT ' . $quote->number . ' bis ' . $quote->valid_until?->format('d.m.Y')
                && str_contains($mail->renderedHtml, 'Begleittext dazu.');
        });
    }

    public function test_template_must_match_document_kind(): void {
        $invoiceTemplate = InvoiceMailTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnung Standard',
            'document_kind' => 'invoice',
            'is_default' => true,
            'subject' => 'RECHNUNG',
            'body_html' => '<p>x</p>',
            'body_text' => 'x',
        ]);

        $quote = $this->approvedQuote();
        $this->actingAs($this->admin)->post(route('quotes.mail', $quote), [
            'to' => ['kunde@example.test'],
            'template_id' => $invoiceTemplate->sqid,
        ])->assertStatus(422);
        Mail::assertNothingQueued();
    }

    public function test_missing_recipient_is_rejected_with_422(): void {
        $quote = $this->approvedQuote();

        $this->actingAs($this->admin)->postJson(route('quotes.mail', $quote), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
        Mail::assertNothingQueued();
    }

    // ── Admin-Vorlagen: Default je (Org, Belegart) ───────────────────────

    public function test_admin_default_templates_are_scoped_per_kind(): void {
        $mkTemplate = fn (string $kind, string $name): array => [
            'name' => $name,
            'document_kind' => $kind,
            'is_default' => '1',
            'subject' => 'S',
            'body_html' => '<p>b</p>',
            'body_text' => 'b',
        ];

        $this->actingAs($this->admin)->post(route('admin.invoice-mail-templates.store'), $mkTemplate('invoice', 'R1'))->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.invoice-mail-templates.store'), $mkTemplate('quote', 'A1'))->assertRedirect();

        // Defaults unterschiedlicher Belegarten koexistieren.
        $this->assertSame(2, InvoiceMailTemplate::query()->where('is_default', true)->count());

        // Zweiter Angebots-Default verdrängt nur den Angebots-Default.
        $this->actingAs($this->admin)->post(route('admin.invoice-mail-templates.store'), $mkTemplate('quote', 'A2'))->assertRedirect();
        $this->assertFalse((bool) InvoiceMailTemplate::query()->where('name', 'A1')->firstOrFail()->is_default);
        $this->assertTrue((bool) InvoiceMailTemplate::query()->where('name', 'R1')->firstOrFail()->is_default);
        $this->assertTrue((bool) InvoiceMailTemplate::query()->where('name', 'A2')->firstOrFail()->is_default);

        // Unbekannte Belegart wird abgelehnt.
        $this->actingAs($this->admin)->post(route('admin.invoice-mail-templates.store'), $mkTemplate('dunning', 'M1'))
            ->assertSessionHasErrors(['document_kind']);
    }
}
