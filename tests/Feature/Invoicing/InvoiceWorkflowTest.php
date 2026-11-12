<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\TaxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 066, MVP-163: §-19-Regel wirkt jetzt auch LOKAL (TaxResolver),
 * optionaler Freigabe-Zwang vor Ausstellung, Mahnstatus nur für
 * überfällige Rechnungen (max. Stufe 3), Zahlungsziel je Rechnung.
 */
final class InvoiceWorkflowTest extends TestCase {
    /** D12: deterministische Nummern statt random_int (Unique-Kollisionsschutz bleibt). */
    private static int $invoiceNo = 0;

    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'country' => 'DE']);
    }

    private function draft(array $overrides = []): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) ++self::$invoiceNo, 4, '0', STR_PAD_LEFT),
            'status' => InvoiceStatus::Draft,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            ...$overrides,
        ]);
        // Feature 160: ein Entwurf ohne Position ist nicht mehr ausstellbar — die Workflow-Tests prüfen den Ausstellungsweg selbst.
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1]);

        return $invoice;
    }

    public function test_small_business_rule_wins_in_tax_resolver(): void {
        $this->org->update(['settings' => ['einvoice' => ['small_business' => '1']]]);

        $result = app(TaxResolver::class)->resolve($this->org->fresh(), $this->customer);

        $this->assertSame('0.00', $result['rate']);
        $this->assertFalse($result['reverse_charge']);
        $this->assertStringContainsString('§ 19', (string) $result['note']);
    }

    public function test_require_approval_blocks_issue_until_approved(): void {
        $this->org->update(['settings' => ['invoicing' => ['require_approval' => '1']]]);
        $invoice = $this->draft(['created_by' => $this->user->id]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status, 'Ohne Freigabe keine Ausstellung.');

        // MVP-1562: Mit Freigabezwang gibt nicht frei, wer die Rechnung angelegt hat.
        $this->actingAs($this->user)->post(route('invoices.approve', $invoice))->assertSessionHas('error');
        $this->assertNull($invoice->fresh()->approved_at);

        $second = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->actingAs($second)->post(route('invoices.approve', $invoice))->assertRedirect();
        $this->assertNotNull($invoice->fresh()->approved_at);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    /** MVP-1624: Eine inhaltliche Änderung nach der Freigabe hebt sie auf (Positionen, Konditionen, Kopf); der Ersteller gibt nicht wieder frei. */
    public function test_changing_the_approved_draft_revokes_the_approval(): void {
        $this->org->update(['settings' => ['invoicing' => ['require_approval' => '1']]]);
        $invoice = $this->draft(['created_by' => $this->user->id]);
        $reviewer = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $approve = function () use ($reviewer, $invoice): void {
            $this->actingAs($reviewer)->post(route('invoices.approve', $invoice))->assertRedirect();
            $this->assertNotNull($invoice->fresh()->approved_at);
        };

        // Position geändert, auch ohne neue Summe.
        $approve();
        $item = $invoice->items()->firstOrFail();
        $this->actingAs($this->user)->put(route('invoices.items.update', [$invoice, $item]), ['description' => 'Leistung geändert', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00'])->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->approved_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'invoice.approval_revoked', 'auditable_id' => $invoice->id]);

        // Konditionen geändert.
        $approve();
        $this->actingAs($this->user)->patch(route('invoices.conditions.update', $invoice), ['discount_percent' => '5'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->approved_at);

        // Kunde oder Datum am Kopf geändert.
        $approve();
        $invoice->fresh()->update(['due_on' => now()->addDays(30)->toDateString()]);
        $this->assertNull($invoice->fresh()->approved_at);

        // Die Selbstfreigabe-Sperre bleibt.
        $this->actingAs($this->user)->post(route('invoices.approve', $invoice))->assertSessionHas('error');
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);

        // Ausstellen nach erneuter Freigabe hebt sie nicht auf.
        $approve();
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $issued = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertNotNull($issued->approved_at);
    }

    /** Ohne Freigabezwang bleibt die Freigabe ein freiwilliger Vermerk — auch durch den Ersteller. */
    public function test_optional_approval_may_be_given_by_the_creator(): void {
        $invoice = $this->draft();

        $this->actingAs($this->user)->post(route('invoices.approve', $invoice))->assertSessionHas('status');
        $this->assertNotNull($invoice->fresh()->approved_at);
    }

    /** MVP-1562: Das Organisationsformular schreibt beide Schalter in dem Format, das die Ausstellung liest. */
    public function test_organization_form_switches_on_approval_and_validation_requirement(): void {
        $registry = app(\App\Settings\SettingsRegistry::class);
        $this->assertTrue($registry->has('invoicing.require_approval'));
        $this->assertTrue($registry->has('einvoice.enforce_validation'));

        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->actingAs($admin)->get(route('admin.organizations.edit', $this->org))
            ->assertOk()
            ->assertSee('settings[invoicing][require_approval]', false)
            ->assertSee('settings[einvoice][enforce_validation]', false);

        $this->actingAs($admin)->put(route('admin.organizations.update', $this->org), [
            'name' => $this->org->name,
            'plan' => $this->org->plan,
            'locale' => $this->org->locale ?? 'de',
            'timezone' => $this->org->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            'settings' => ['invoicing' => ['require_approval' => '1'], 'einvoice' => ['enforce_validation' => '1']],
        ])->assertSessionHasNoErrors();

        $settings = $this->org->refresh()->settings;
        $this->assertSame('1', (string) data_get($settings, 'invoicing.require_approval'));
        $this->assertSame('1', (string) data_get($settings, 'einvoice.enforce_validation'));

        $invoice = $this->draft();
        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    /**
     * Vollscan 2026-08-23, B1: Versand und Lexoffice-Push stellten Rechnungen
     * ohne tax_context-Freeze und am Freigabe-Zwang vorbei aus. Alle Pfade
     * laufen jetzt über InvoiceIssueService.
     */
    public function test_sending_a_draft_issues_it_with_a_frozen_tax_context(): void {
        \Illuminate\Support\Facades\Mail::fake();
        $template = \App\Models\Invoicing\InvoiceMailTemplate::query()->create([
            'organization_id' => null, 'name' => 'T', 'is_default' => true,
            'subject' => 'R {{invoice_number}}', 'body_html' => '<p>x</p>', 'body_text' => 'x',
        ]);
        $invoice = $this->draft();

        $this->actingAs($this->user)->post(route('invoices.send', $invoice), [
            'template_id' => $template->id,
            'to' => ['kunde@example.test'],
        ])->assertRedirect(route('invoices.show', $invoice));

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Issued, $fresh->status);
        $this->assertNotNull($fresh->party_snapshot);
        $this->assertNotNull($fresh->due_on);
        $this->assertIsArray($fresh->tax_context);
        $this->assertSame('19.00', (string) ($fresh->tax_context['rate'] ?? ''));
        $this->assertArrayHasKey('resolved_on', $fresh->tax_context);
    }

    public function test_sending_respects_the_approval_requirement(): void {
        \Illuminate\Support\Facades\Mail::fake();
        $this->org->update(['settings' => ['invoicing' => ['require_approval' => '1']]]);
        $template = \App\Models\Invoicing\InvoiceMailTemplate::query()->create([
            'organization_id' => null, 'name' => 'T', 'is_default' => true,
            'subject' => 'R {{invoice_number}}', 'body_html' => '<p>x</p>', 'body_text' => 'x',
        ]);
        $invoice = $this->draft();

        $this->actingAs($this->user)->post(route('invoices.send', $invoice), [
            'template_id' => $template->id,
            'to' => ['kunde@example.test'],
        ])->assertSessionHas('error');

        \Illuminate\Support\Facades\Mail::assertNothingQueued();
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status, 'Ohne Freigabe verlässt keine Rechnung das Haus.');
    }

    public function test_mark_sent_issues_through_the_single_write_path(): void {
        $invoice = $this->draft();

        $invoice->markSent();

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Issued, $fresh->status);
        $this->assertSame(1, (int) $fresh->sent_count);
        $this->assertIsArray($fresh->tax_context);
    }

    public function test_payment_terms_days_drive_due_date(): void {
        $invoice = $this->draft(['payment_terms_days' => 30]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $invoice->fresh()->due_on->toDateString(),
        );
    }

    public function test_dunning_only_for_overdue_and_capped(): void {
        $notDue = $this->draft(['status' => InvoiceStatus::Issued, 'issued_on' => now(), 'due_on' => now()->addWeek()]);
        $this->actingAs($this->user)->post(route('invoices.dun', $notDue));
        $this->assertSame(0, (int) $notDue->fresh()->dunning_level, 'Nicht fällig → keine Mahnung.');

        $overdue = $this->draft(['status' => InvoiceStatus::Issued, 'issued_on' => now()->subMonth(), 'due_on' => now()->subWeek()]);
        foreach ([1, 2, 3] as $level) {
            $this->actingAs($this->user)->post(route('invoices.dun', $overdue))->assertRedirect();
            $this->assertSame($level, (int) $overdue->fresh()->dunning_level);
        }

        // Stufe 4 gibt es nicht.
        $this->actingAs($this->user)->post(route('invoices.dun', $overdue));
        $this->assertSame(3, (int) $overdue->fresh()->dunning_level);
        $this->assertNotNull($overdue->fresh()->dunned_at);
    }

    /** Sicherheitsaudit 2026-10-21, li-1: eine zweite, veraltete Instanz desselben Entwurfs zieht keine zweite Nummer. */
    public function test_a_stale_second_issue_draws_no_second_number(): void {
        $invoice = $this->draft(['number' => \App\Services\Numbering\NumberSequenceService::DRAFT_PREFIX . 'STALE1']);
        $first = Invoice::query()->findOrFail($invoice->id);
        $stale = Invoice::query()->findOrFail($invoice->id);
        $issuer = app(\App\Services\Invoicing\InvoiceIssueService::class);

        $issuer->issue($first);
        $number = (string) $first->fresh()->number;
        $drawn = (int) \Illuminate\Support\Facades\DB::table('number_sequences')->where('organization_id', $this->org->id)->sum('last_value');
        $issuer->issue($stale);

        $this->assertFalse(\App\Services\Numbering\NumberSequenceService::isDraft($number));
        $this->assertSame($number, $stale->number, 'Die veraltete Instanz sieht unter der Sperre den ausgestellten Stand.');
        $this->assertSame($number, (string) $invoice->fresh()->number);
        $this->assertSame($drawn, (int) \Illuminate\Support\Facades\DB::table('number_sequences')->where('organization_id', $this->org->id)->sum('last_value'), 'Keine zweite Nummer gezogen.');
    }
}
