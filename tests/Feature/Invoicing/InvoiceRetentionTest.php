<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceRetentionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\Invoicing\InvoiceRetention;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Services\Finance\MatchingService;
use App\Services\Invoicing\{InvoicePdfRenderer, RetentionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
 *
 * Der Kern ist nicht das Anlegen, sondern die Wirkung: Ein Einbehalt ist
 * **kein offener Posten**. Ohne das meldet der Zahlungsabgleich dauerhaft
 * eine Unterzahlung und das Mahnwesen mahnt einen Betrag an, den der Kunde
 * zu Recht nicht zahlt.
 */
class InvoiceRetentionTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function invoice(string $status = Invoice::STATUS_DRAFT): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-' . uniqid(),
            'status' => $status,
            'currency' => 'EUR',
            'tax_rate' => '0.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Rohbau',
            'quantity' => '1.000',
            'unit_price' => '10000.0000',
            'tax_rate' => '0.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->refresh();
    }

    public function test_percentage_retention_is_calculated_from_the_total(): void {
        $invoice = $this->invoice();

        $retention = app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYears(5)->toDateString(), $this->admin);

        $this->assertSame('500.00', $retention->amount->getAmount());
        $this->assertSame('10000.00', $retention->base_amount->getAmount());
        $this->assertSame(RetentionStatus::Open, $retention->status);
    }

    public function test_fixed_amount_retention_is_taken_verbatim(): void {
        $retention = app(RetentionService::class)->add($this->invoice(), RetentionKind::Performance, null, 750.0, null, $this->admin);

        $this->assertSame('750.00', $retention->amount->getAmount());
        $this->assertNull($retention->percent);
    }

    public function test_both_or_neither_basis_is_refused(): void {
        $invoice = $this->invoice();
        $service = app(RetentionService::class);

        $this->expectException(\RuntimeException::class);
        $service->add($invoice, RetentionKind::Warranty, 5.0, 500.0, null, $this->admin);
    }

    public function test_retentions_may_not_exceed_the_invoice_total(): void {
        $invoice = $this->invoice();
        $service = app(RetentionService::class);
        $service->add($invoice, RetentionKind::Warranty, null, 9000.0, null, $this->admin);

        $this->expectException(\RuntimeException::class);
        $service->add($invoice->refresh(), RetentionKind::Performance, null, 2000.0, null, $this->admin);
    }

    /** Nach dem Ausstellen ist der Einbehalt Beleginhalt und eingefroren. */
    public function test_retention_cannot_be_added_after_issuing(): void {
        $invoice = $this->invoice(Invoice::STATUS_ISSUED);

        $this->expectException(\RuntimeException::class);
        app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, null, $this->admin);
    }

    // ── Die eigentliche Wirkung ─────────────────────────────────────────

    public function test_payment_of_the_reduced_amount_counts_as_full_payment(): void {
        $invoice = $this->invoice();
        app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYears(5)->toDateString(), $this->admin);
        $invoice->refresh();

        $this->assertSame(9500.0, app(RetentionService::class)->payableAmountOf($invoice));

        // Der Abgleich rechnet auf dem GEMINDERTEN Betrag weiter: Die
        // pauschale Skonto-Toleranz (3 %) greift auf 9.500, nicht auf 10.000 —
        // ohne den Abzug läge die Untergrenze bei 9.700 und eine korrekte
        // Zahlung von 9.500 wäre dauerhaft eine Unterzahlung.
        $this->assertSame(
            round(9500.0 * (1 - MatchingService::SKONTO_PERCENT / 100), 2),
            round(app(MatchingService::class)->minAcceptableFor($invoice), 2),
        );
    }

    /** Nach dem Freigabetermin zählt wieder der volle Betrag. */
    public function test_an_overdue_retention_no_longer_reduces_the_claim(): void {
        $invoice = $this->invoice();
        $retention = app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYear()->toDateString(), $this->admin);
        $retention->forceFill(['due_on' => now()->subDay()->toDateString()])->save();

        $this->assertSame(0.0, app(RetentionService::class)->openAmountOf($invoice->refresh()));
    }

    public function test_released_retention_no_longer_reduces_the_claim(): void {
        $invoice = $this->invoice();
        $retention = app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYears(5)->toDateString(), $this->admin);

        app(RetentionService::class)->release($retention, $this->admin);

        $this->assertSame(0.0, app(RetentionService::class)->openAmountOf($invoice->refresh()));
        $this->assertSame(RetentionStatus::Released, $retention->refresh()->status);
    }

    public function test_dunning_letter_excludes_the_retention(): void {
        $invoice = $this->invoice();
        app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYears(5)->toDateString(), $this->admin);
        $invoice->forceFill(['status' => Invoice::STATUS_ISSUED, 'due_on' => now()->subDays(20)->toDateString()])->save();

        $html = view('invoices.dunning-pdf', app(\App\Services\Invoicing\DunningPdfRenderer::class)
            ->viewData($invoice->refresh()->load(['customer', 'retentions']), 1))->render();

        $this->assertStringContainsString('9.500,00', $html, 'Gemahnt wird der Zahlbetrag, nicht die Rechnungssumme.');
        $this->assertStringContainsString('500,00', $html, 'Die Abzugszeile belegt, dass der Einbehalt bekannt ist.');
    }

    public function test_invoice_pdf_shows_the_retention_and_the_payable_amount(): void {
        $invoice = $this->invoice();
        app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addYears(5)->toDateString(), $this->admin);

        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice->refresh()->load('retentions')))->render();

        $this->assertStringContainsString('§ 17 VOB/B', $html);
        $this->assertStringContainsString('9.500,00', $html);
    }

    public function test_scanner_reminds_about_a_due_release(): void {
        \Illuminate\Support\Facades\Notification::fake();
        // Empfänger ist die Buchhaltung — der Einbehalt ist eine
        // kaufmännische Frist, keine Sache des Auftragsverantwortlichen.
        $accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        NotificationRule::factory()->forEvent(NotificationEvent::RetentionReleaseDue)->create([
            'organization_id' => $this->org->id,
            'channels' => [NotificationChannel::InApp->value],
            'recipient_user_ids' => [$accounting->id],
        ]);

        $invoice = $this->invoice();
        app(RetentionService::class)->add($invoice, RetentionKind::Warranty, 5.0, null, now()->addDays(10)->toDateString(), $this->admin);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::RetentionReleaseDue->value,
            'stage' => NotificationDispatchLog::STAGE_INITIAL,
        ]);
    }

    public function test_endpoint_creates_and_releases(): void {
        $invoice = $this->invoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.retentions.store', $invoice), [
                'kind' => RetentionKind::Warranty->value,
                'basis' => 'percent',
                'percent' => '5',
                'due_on' => now()->addYears(5)->toDateString(),
            ])->assertRedirect();

        $retention = InvoiceRetention::query()->sole();
        $this->assertSame('500.00', $retention->amount->getAmount());

        $this->actingAs($this->admin)
            ->post(route('invoices.retentions.release', [$invoice, $retention]))
            ->assertRedirect();

        $this->assertSame(RetentionStatus::Released, $retention->refresh()->status);
    }
}
