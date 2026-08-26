<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\{CommissionAssignmentSource, CommissionScope, CommissionSettlementStatus, CommissionStatus};
use App\Models\{Article, Customer, Invoice, Lead, Organization, User};
use App\Models\Sales\{CommissionRule, InvoiceCommission};
use App\Services\Sales\{CommissionAccrualService, CommissionRuleResolver, CommissionSettlementService};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Provisionen (Feature 146, MVP-729).
 *
 * Kern der Pruefung: Die Provision entsteht **an der bezahlten Rechnung**,
 * genau EINE Regel gewinnt, gerechnet wird exakt (bc, nie float), Storno und
 * Gutschrift erzeugen eine Rueckrechnung statt einer Korrektur, und ein
 * geschlossener Lauf ist festgeschrieben.
 */
final class CommissionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $seller;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->seller = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Vera Vertrieb']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH']);
    }

    // ── Hilfen ───────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    private function rule(array $attributes = []): CommissionRule {
        return CommissionRule::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'name' => 'Grundsatz',
            'scope' => CommissionScope::All,
            'rate_percent' => '5.00',
            'priority' => 100,
            'is_active' => true,
        ], $attributes));
    }

    /** Lead, der den Kunden angebahnt hat — Herkunft der Zuordnung. */
    private function lead(?User $responsible = null, string $source = 'referral'): Lead {
        return Lead::query()->create([
            'organization_id' => $this->organization->id,
            'company' => 'Muster GmbH',
            'source' => $source,
            'status' => 'converted',
            'customer_id' => $this->customer->id,
            'responsible_user_id' => ($responsible ?? $this->seller)->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(array $attributes = []): Invoice {
        return Invoice::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999),
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'currency' => 'EUR',
            'subtotal' => '1000.00',
            'tax_rate' => '19.00',
            'total' => '1190.00',
            'issued_on' => Carbon::parse('2026-08-01'),
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function pay(Invoice $invoice, string $on = '2026-08-15'): Invoice {
        $invoice->status = Invoice::STATUS_PAID;
        $invoice->paid_on = Carbon::parse($on);
        $invoice->save();

        return $invoice->refresh();
    }

    // ── Entstehung ───────────────────────────────────────────────────────

    /** Die Naht: erst „bezahlt" erzeugt eine Provision, „ausgestellt" nie. */
    public function test_commission_appears_only_when_the_invoice_is_paid(): void {
        $this->rule();
        $this->lead();
        $invoice = $this->invoice();

        $this->assertSame(0, InvoiceCommission::query()->count(), 'Die ausgestellte Rechnung darf noch keine Provision erzeugen.');

        $this->pay($invoice);

        $commission = InvoiceCommission::query()->firstOrFail();
        $this->assertSame($this->seller->id, $commission->user_id);
        $this->assertSame(CommissionAssignmentSource::Lead, $commission->assignment_source);
        $this->assertSame('1000.00', $commission->base_amount?->getAmount());
        $this->assertSame('50.00', $commission->commission_amount?->getAmount());
        $this->assertSame('2026-08-15', $commission->earned_on?->toDateString());
        $this->assertSame(CommissionStatus::Pending, $commission->status);
    }

    /** Ohne Zuordnung (kein Lead, keine manuelle Person) entsteht nichts. */
    public function test_without_an_assignment_no_commission_is_created(): void {
        $this->rule();
        $this->pay($this->invoice());

        $this->assertSame(0, InvoiceCommission::query()->count());
    }

    /** Ein zweiter Statuswechsel auf „bezahlt" verdoppelt die Provision nicht. */
    public function test_accrual_is_idempotent(): void {
        $this->rule();
        $this->lead();
        $invoice = $this->pay($this->invoice());

        app(CommissionAccrualService::class)->accrue($invoice);
        app(CommissionAccrualService::class)->accrue($invoice);

        $this->assertSame(1, InvoiceCommission::query()->count());
    }

    /** Exakt gerechnet (bc über Money/Percentage), kein float. */
    public function test_amount_is_calculated_exactly(): void {
        $this->rule(['rate_percent' => '3.33']);
        $this->lead();

        $this->pay($this->invoice(['subtotal' => '1234.57', 'total' => '1469.14']));

        $this->assertSame('41.11', InvoiceCommission::query()->firstOrFail()->commission_amount?->getAmount());
    }

    // ── Regelauswahl ─────────────────────────────────────────────────────

    /** Höchste Priorität gewinnt; bei Gleichstand der engere Geltungsbereich. */
    public function test_rule_selection_follows_priority_then_specificity(): void {
        $this->rule(['name' => 'Grundsatz', 'rate_percent' => '5.00', 'priority' => 100]);
        $special = $this->rule([
            'name' => 'Empfehlungen',
            'scope' => CommissionScope::LeadSource,
            'scope_value' => 'referral',
            'rate_percent' => '9.00',
            'priority' => 200,
        ]);
        $this->lead();

        $this->pay($this->invoice());

        $commission = InvoiceCommission::query()->firstOrFail();
        $this->assertSame($special->id, $commission->commission_rule_id);
        $this->assertSame('90.00', $commission->commission_amount?->getAmount());
    }

    public function test_specificity_breaks_the_tie_at_equal_priority(): void {
        $this->rule(['name' => 'Grundsatz', 'rate_percent' => '5.00', 'priority' => 100]);
        $personal = $this->rule([
            'name' => 'Vera persönlich',
            'scope' => CommissionScope::User,
            'user_id' => $this->seller->id,
            'rate_percent' => '7.00',
            'priority' => 100,
        ]);
        $this->lead();

        $this->pay($this->invoice());

        $this->assertSame($personal->id, InvoiceCommission::query()->firstOrFail()->commission_rule_id);
    }

    /** Regeln außerhalb ihres Gültigkeitszeitraums zählen nicht. */
    public function test_expired_rules_are_ignored(): void {
        $this->rule(['rate_percent' => '5.00', 'valid_to' => '2026-07-31']);
        $this->lead();

        $this->pay($this->invoice());

        $this->assertSame(0, InvoiceCommission::query()->count());
    }

    /** Produktgruppen-Regel: Grundlage sind NUR die Positionen dieser Gruppe. */
    public function test_product_group_rule_uses_only_the_matching_positions(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'category' => 'Wartung']);
        $this->rule([
            'name' => 'Wartung',
            'scope' => CommissionScope::ProductGroup,
            'scope_value' => 'Wartung',
            'rate_percent' => '10.00',
            'priority' => 300,
        ]);
        $this->lead();

        $invoice = $this->invoice();
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'description' => 'Wartungspauschale',
            'quantity' => '1.000',
            'unit_price' => '400.0000',
            'amount' => '400.00',
            'position' => 1,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Sonstiges',
            'quantity' => '1.000',
            'unit_price' => '600.0000',
            'amount' => '600.00',
            'position' => 2,
        ]);

        $this->pay($invoice);

        $commission = InvoiceCommission::query()->firstOrFail();
        $this->assertSame('400.00', $commission->base_amount?->getAmount());
        $this->assertSame('40.00', $commission->commission_amount?->getAmount());
    }

    // ── Zuordnung ────────────────────────────────────────────────────────

    /** Die manuelle Zuordnung schlägt die Herkunft aus der Lead-Pipeline. */
    public function test_manual_assignment_beats_the_lead_origin(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Manuel Manuell']);
        $this->rule();
        $this->lead();
        $invoice = $this->pay($this->invoice());

        app(CommissionAccrualService::class)->assign($invoice, $other);

        $open = InvoiceCommission::query()->where('status', CommissionStatus::Pending->value)->get();
        $this->assertCount(1, $open);
        $this->assertSame($other->id, $open->first()?->user_id);
        $this->assertSame(CommissionAssignmentSource::Manual, $open->first()?->assignment_source);
        // Die alte, noch nicht abgerechnete Zeile ist samt Rückrechnung neutralisiert.
        $this->assertSame(2, InvoiceCommission::query()->where('status', CommissionStatus::Reversed->value)->count());
    }

    public function test_assignment_form_and_post_work_over_http(): void {
        $this->rule();
        $invoice = $this->pay($this->invoice());

        $this->actingAs($this->admin)
            ->get(route('commissions.assign.form', $invoice))
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('commissions.assign', $invoice), ['user_id' => $this->seller->sqid])
            ->assertRedirect();

        $this->assertSame($this->seller->id, $invoice->refresh()->sales_user_id);
        $this->assertSame(1, InvoiceCommission::query()->count());
    }

    // ── Rückrechnung ─────────────────────────────────────────────────────

    /** Gutschrift auf eine noch offene Provision: Teilbetrag mindert die Periode. */
    public function test_credit_note_creates_a_partial_reversal(): void {
        $this->rule(['rate_percent' => '10.00']);
        $this->lead();
        $invoice = $this->pay($this->invoice());

        $credit = $this->invoice([
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'parent_invoice_id' => $invoice->id,
            'subtotal' => '400.00',
            'total' => '476.00',
        ]);
        $this->pay($credit, '2026-08-20');

        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->firstOrFail();
        $this->assertSame('-400.00', $reversal->base_amount?->getAmount());
        $this->assertSame('-40.00', $reversal->commission_amount?->getAmount());
        // Teilrückrechnung: beide Zeilen bleiben offen und saldieren in der Periode.
        $this->assertSame(CommissionStatus::Pending, $reversal->status);
        $this->assertSame(CommissionStatus::Pending, InvoiceCommission::query()->whereNull('reversal_of_id')->firstOrFail()->status);
    }

    /** Storno einer noch offenen Provision: beide Zeilen fallen aus dem Lauf. */
    public function test_cancellation_neutralises_an_open_commission(): void {
        $this->rule(['rate_percent' => '10.00']);
        $this->lead();
        $invoice = $this->pay($this->invoice());

        $invoice->status = Invoice::STATUS_CANCELLED;
        $invoice->cancelled_at = Carbon::parse('2026-09-02');
        $invoice->save();

        $this->assertSame(2, InvoiceCommission::query()->count());
        $this->assertSame(2, InvoiceCommission::query()->where('status', CommissionStatus::Reversed->value)->count());
    }

    /** Storno NACH dem Abschluss: die Rückrechnung fällt in die Folgeperiode. */
    public function test_cancellation_after_settlement_falls_into_the_next_period(): void {
        $this->rule(['rate_percent' => '10.00']);
        $this->lead();
        $invoice = $this->pay($this->invoice());

        $settlement = app(CommissionSettlementService::class);
        $august = $settlement->createRun(
            $this->organization,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            CurrencyCode::Euro,
            '2026-08',
            $this->admin,
        );
        $settlement->close($august, $this->admin);

        $invoice->refresh();
        $invoice->status = Invoice::STATUS_CANCELLED;
        $invoice->cancelled_at = Carbon::parse('2026-09-02');
        $invoice->save();

        // Der geschlossene Lauf bleibt unverändert.
        $august->refresh();
        $this->assertSame('100.00', $august->total_commission?->getAmount());
        $this->assertSame(1, $august->entry_count);
        $this->assertSame(CommissionStatus::Settled, InvoiceCommission::query()->whereNull('reversal_of_id')->firstOrFail()->status);

        // Die Rückrechnung wartet offen auf den September-Lauf.
        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->firstOrFail();
        $this->assertSame(CommissionStatus::Pending, $reversal->status);
        $this->assertSame('-100.00', $reversal->commission_amount?->getAmount());

        $september = $settlement->createRun(
            $this->organization,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30'),
            CurrencyCode::Euro,
            '2026-09',
            $this->admin,
        );
        $settlement->close($september, $this->admin);

        $this->assertSame('-100.00', $september->refresh()->total_commission?->getAmount());
    }

    // ── Abrechnungslauf ──────────────────────────────────────────────────

    public function test_closing_a_run_freezes_it(): void {
        $this->rule(['rate_percent' => '10.00']);
        $this->lead();
        $this->pay($this->invoice());

        $settlement = app(CommissionSettlementService::class);
        $run = $settlement->createRun(
            $this->organization,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            CurrencyCode::Euro,
            '2026-08',
            $this->admin,
        );

        $this->assertCount(1, $settlement->rowsOf($run), 'Der Entwurf zeigt die offenen Zeilen als Vorschau.');

        $settlement->close($run, $this->admin);
        $run->refresh();

        $this->assertSame(CommissionSettlementStatus::Closed, $run->status);
        $this->assertSame('1000.00', $run->total_base?->getAmount());
        $this->assertSame('100.00', $run->total_commission?->getAmount());
        $this->assertSame($this->admin->id, $run->closed_by);
        $this->assertSame(CommissionStatus::Settled, InvoiceCommission::query()->firstOrFail()->status);

        // Zweiter Abschluss und inhaltliche Änderung sind gesperrt.
        $this->expectException(RuntimeException::class);
        $settlement->close($run, $this->admin);
    }

    public function test_closed_run_cannot_be_changed_or_deleted(): void {
        $settlement = app(CommissionSettlementService::class);
        $run = $settlement->createRun(
            $this->organization,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
            CurrencyCode::Euro,
            '2026-08',
            $this->admin,
        );
        $settlement->close($run, $this->admin);

        $run->refresh();
        $run->period = 'manipuliert';

        $this->expectException(RuntimeException::class);
        $run->save();
    }

    public function test_overlapping_runs_are_rejected(): void {
        $settlement = app(CommissionSettlementService::class);
        $settlement->createRun($this->organization, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), CurrencyCode::Euro, '2026-08', $this->admin);

        $this->expectException(RuntimeException::class);
        $settlement->createRun($this->organization, Carbon::parse('2026-08-15'), Carbon::parse('2026-09-15'), CurrencyCode::Euro, 'überlappend', $this->admin);
    }

    // ── Export ───────────────────────────────────────────────────────────

    /** CSV für die Lohnabrechnung: Rückrechnung als echter Minusbetrag. */
    public function test_csv_export_contains_the_rows_with_signed_amounts(): void {
        $this->rule(['rate_percent' => '10.00']);
        $this->lead();
        $invoice = $this->pay($this->invoice());

        $credit = $this->invoice([
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'parent_invoice_id' => $invoice->id,
            'subtotal' => '400.00',
            'total' => '476.00',
        ]);
        $this->pay($credit, '2026-08-20');

        $settlement = app(CommissionSettlementService::class);
        $run = $settlement->createRun($this->organization, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), CurrencyCode::Euro, '2026-08', $this->admin);
        $settlement->close($run, $this->admin);

        $csv = $settlement->exportCsv($run->refresh());

        $this->assertStringContainsString('Vera Vertrieb', $csv);
        $this->assertStringContainsString(';100.00;', $csv);
        // Der Minusbetrag darf NICHT vom Formel-Guard mit Apostroph entwertet werden.
        $this->assertStringContainsString(';-40.00;', $csv);
        $this->assertStringNotContainsString("'-40.00", $csv);

        $this->actingAs($this->admin)
            ->get(route('commission-runs.export', $run))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ── UI / Rechte / Mandant ────────────────────────────────────────────

    public function test_pages_render_for_an_admin(): void {
        $this->rule();
        $this->lead();
        $this->pay($this->invoice());

        $this->actingAs($this->admin)->get(route('commission-rules.index'))->assertOk()->assertSee('Grundsatz');
        $this->actingAs($this->admin)->get(route('commissions.index'))->assertOk()->assertSee('Vera Vertrieb');
        $this->actingAs($this->admin)->get(route('commission-runs.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('commission-rules.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('commission-runs.create'))->assertOk();
    }

    public function test_rule_can_be_created_and_deleted_over_http(): void {
        $this->actingAs($this->admin)->post(route('commission-rules.store'), [
            'name' => 'Neue Regel',
            'scope' => CommissionScope::LeadSource->value,
            'scope_value' => 'referral',
            'rate_percent' => '4.50',
            'priority' => 150,
            'is_active' => '1',
        ])->assertRedirect(route('commission-rules.index'));

        $rule = CommissionRule::query()->firstOrFail();
        $this->assertSame(CommissionScope::LeadSource, $rule->scope);
        $this->assertSame('4.50', $rule->rate_percent?->getNumericValue());

        $this->actingAs($this->admin)->delete(route('commission-rules.destroy', $rule))->assertRedirect();
        $this->assertSame(0, CommissionRule::query()->count());
    }

    /** Freitext als Lead-Quelle würde auf keinen Beleg passen — abgewiesen. */
    public function test_lead_source_rule_rejects_an_unknown_source(): void {
        $this->actingAs($this->admin)->post(route('commission-rules.store'), [
            'name' => 'Unsinn',
            'scope' => CommissionScope::LeadSource->value,
            'scope_value' => 'gibt-es-nicht',
            'rate_percent' => '4.50',
            'priority' => 150,
        ])->assertSessionHasErrors('scope_value');

        $this->assertSame(0, CommissionRule::query()->count());
    }

    public function test_plain_user_without_commission_rights_is_denied(): void {
        $this->actingAs($this->seller)->get(route('commission-rules.index'))->assertForbidden();
        $this->actingAs($this->seller)->get(route('commission-runs.index'))->assertForbidden();
    }

    public function test_foreign_organization_commissions_are_invisible(): void {
        $this->rule();
        $this->lead();
        $this->pay($this->invoice());

        $other = Organization::factory()->create();
        $otherUser = User::factory()->user()->create(['organization_id' => $other->id, 'name' => 'Fremd Fritz']);
        $otherCustomer = Customer::factory()->create(['organization_id' => $other->id, 'name' => 'Fremd AG']);
        InvoiceCommission::query()->create([
            'organization_id' => $other->id,
            'invoice_id' => Invoice::query()->create([
                'organization_id' => $other->id,
                'customer_id' => $otherCustomer->id,
                'number' => 'FR-1',
                'status' => Invoice::STATUS_PAID,
                'currency' => 'EUR',
                'subtotal' => '500.00',
                'total' => '595.00',
            ])->id,
            'user_id' => $otherUser->id,
            'currency' => 'EUR',
            'base_amount' => '500.00',
            'rate_percent' => '5.00',
            'commission_amount' => '25.00',
            'earned_on' => '2026-08-10',
            'status' => CommissionStatus::Pending,
        ]);

        $this->actingAs($this->admin)
            ->get(route('commissions.index'))
            ->assertOk()
            ->assertSee('Vera Vertrieb')
            ->assertDontSee('Fremd Fritz');

        // Auch der Lauf der eigenen Organisation zieht keine fremde Zeile.
        $settlement = app(CommissionSettlementService::class);
        $run = $settlement->createRun($this->organization, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), CurrencyCode::Euro, '2026-08', $this->admin);
        $settlement->close($run, $this->admin);

        $this->assertSame(1, $run->refresh()->entry_count);
    }

    /** Die Herkunft aus der Lead-Pipeline ist auch ohne Provision auflösbar. */
    public function test_resolver_reports_the_lead_origin(): void {
        $lead = $this->lead();
        $invoice = $this->invoice();

        $assignment = app(CommissionRuleResolver::class)->assignmentFor($invoice);

        $this->assertNotNull($assignment);
        $this->assertSame($this->seller->id, $assignment?->user->id);
        $this->assertSame($lead->id, $assignment?->lead?->id);
        $this->assertSame(CommissionAssignmentSource::Lead, $assignment?->source);
    }
}
