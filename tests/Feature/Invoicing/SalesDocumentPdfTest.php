<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesDocumentPdfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Mail\DunningMail;
use App\Models\{Customer, Invoice, Organization, Quote, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use App\Services\Invoicing\{DunningPdfRenderer, OrderConfirmationPdfRenderer, QuotePdfRenderer, QuoteService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MVP-650 (Issue #83): eigene PDF-Generatoren für Angebot,
 * Auftragsbestätigung und Mahnung — die drei bisher nur „geplanten"
 * Vertriebsbeleg-Arten der Dokumentarten-Registrierung.
 */
class SalesDocumentPdfTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create([
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
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function quote(): Quote {
        return app(QuoteService::class)->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
            'terms' => 'Lieferzeit 4 Wochen ab Auftragseingang.',
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => '19.00'],
            ['description' => 'Fachliteratur', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => '7.00'],
            ['description' => 'Schulung (optional)', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => '19.00', 'optional' => true],
        ], $this->user);
    }

    public function test_quote_pdf_view_shows_items_optional_marker_and_tax_breakdown(): void {
        $quote = $this->quote();

        $html = view('quotes.pdf', app(QuotePdfRenderer::class)->viewData($quote))->render();

        $this->assertStringContainsString($quote->number, $html);
        $this->assertStringContainsString('Grundpaket', $html);
        $this->assertStringContainsString('Gültig bis', $html);
        $this->assertStringContainsString('Option — nicht in der Gesamtsumme enthalten', $html);
        $this->assertStringContainsString('Lieferzeit 4 Wochen', $html);
        // Steueraufriss je Satz über die ZÄHLENDEN Positionen (Option zählt nicht).
        $this->assertStringContainsString('USt. 19%', $html);
        $this->assertStringContainsString('USt. 7%', $html);
        $this->assertStringContainsString('1.100,00 EUR', $html, 'Zwischensumme ohne Option.');
    }

    public function test_quote_pdf_route_downloads_and_denies_cross_org(): void {
        $quote = $this->quote();

        $response = $this->actingAs($this->user)->get(route('quotes.pdf', $quote));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->buchhaltung()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreign)->get(route('quotes.pdf', $quote))->assertNotFound();
    }

    public function test_order_confirmation_requires_acceptance_and_lists_only_accepted_items(): void {
        $service = app(QuoteService::class);
        $quote = $this->quote();

        // Vor der Annahme: keine Auftragsbestätigung.
        $this->actingAs($this->user)->get(route('quotes.order-confirmation', $quote))->assertStatus(422);

        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $firstItem = $quote->items()->orderBy('position')->firstOrFail();
        // Teilannahme: Grundpaket ja, Fachliteratur nein, Option nein.
        $quote = $service->accept($quote, [(int) $firstItem->id], $token);
        $this->assertSame('partially_accepted', $quote->status);

        $html = view('quotes.order-confirmation-pdf', app(OrderConfirmationPdfRenderer::class)->viewData($quote))->render();
        $this->assertStringContainsString('Auftragsbestätigung', $html);
        $this->assertStringContainsString('Grundpaket', $html);
        $this->assertStringNotContainsString('Fachliteratur', $html, 'Nicht angenommene Positionen erscheinen nicht.');
        $this->assertStringContainsString('Teilannahme', $html);
        $this->assertStringContainsString($quote->number, $html);

        $response = $this->actingAs($this->user)->get(route('quotes.order-confirmation', $quote));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function overdueInvoice(): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0100',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => now()->subDays(40)->toDateString(),
            'due_on' => now()->subDays(20)->toDateString(),
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '1.000',
            'unit_price' => '1000.0000',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    public function test_dunning_letter_lists_claim_fee_and_deadline(): void {
        $invoice = $this->overdueInvoice();

        $html = view('invoices.dunning-pdf', app(DunningPdfRenderer::class)->viewData(
            $invoice,
            2,
            'Bitte überweisen Sie umgehend.',
            12.50,
            now()->addDays(10)->toImmutable(),
        ))->render();

        $this->assertStringContainsString('2. Mahnung', $html);
        $this->assertStringContainsString($invoice->number, $html);
        $this->assertStringContainsString('Zahlbar bis', $html);
        $this->assertStringContainsString('Mahngebühr', $html);
        $this->assertStringContainsString('12,50 EUR', $html);
        // Gesamtforderung = offener Betrag (1190,00) + Gebühr.
        $this->assertStringContainsString('1.202,50 EUR', $html);
        $this->assertStringContainsString('Bitte überweisen Sie umgehend.', $html);
        $this->assertStringContainsString('DE02120300000000202051', $html, 'Bankverbindung fürs Zahlungsziel.');
    }

    public function test_dun_action_queues_mail_with_letter_and_invoice_attachments(): void {
        Mail::fake();
        $invoice = $this->overdueInvoice();

        $this->actingAs($this->user)->post(route('invoices.dun', $invoice), [
            'send_mail' => 1,
            'email' => 'kunde@example.com',
            'fee' => '12.50',
            'pay_until' => now()->addDays(10)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, (int) $invoice->refresh()->dunning_level);
        Mail::assertQueued(DunningMail::class, function (DunningMail $mail): bool {
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());

            return $mail->fee === 12.5
                && $mail->payUntil !== null
                && $names === ['zahlungserinnerung-R2026-0100.pdf', 'rechnung-R2026-0100.pdf'];
        });
        $this->assertDatabaseHas('document_dispatches', [
            'invoice_id' => $invoice->id,
        ]);
        $dispatch = \App\Models\DocumentDispatch::query()->firstOrFail();
        $this->assertSame('dunning', $dispatch->meta['kind'] ?? null);
        $this->assertSame(12.5, (float) ($dispatch->meta['fee'] ?? 0));
    }

    public function test_sent_quote_keeps_frozen_design_snapshot(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $profiles = app(RenderProfileService::class);
        $profile = $profiles->createProfile($this->org, 'CI-Basisdesign', [], true, $admin);
        $this->assertTrue($profiles->activate($profile->versions()->firstOrFail(), $admin)->ok());
        $frozenVersionId = $profile->refresh()->active_version_id;

        $service = app(QuoteService::class);
        $quote = $this->quote();
        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote] = $service->send($quote, $this->user);

        $this->assertDatabaseHas('document_render_snapshots', [
            'documentable_id' => $quote->id,
            'document_kind' => RenderDocumentKind::Quote->value,
            'profile_version_id' => $frozenVersionId,
        ]);

        // Späterer Profilwechsel ändert das versandte Angebot nicht.
        $newDraft = $profiles->newDraftFrom($profile->versions()->firstOrFail()->refresh(), $admin);
        $this->assertTrue($profiles->activate($newDraft, $admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFromSnapshot($quote, RenderDocumentKind::Quote);
        $this->assertNotNull($payload);
        $this->assertSame($frozenVersionId, $payload['profile_version_id']);
    }
}
