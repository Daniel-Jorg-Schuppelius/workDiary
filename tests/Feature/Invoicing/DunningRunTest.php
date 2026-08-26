<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Mail\DunningMail;
use App\Models\{AuditLog, Customer, DocumentDispatch, Invoice, Organization, User};
use App\Services\Invoicing\DunningService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mahnlauf (Feature 127, MVP-691 — Vollscan H8): Kandidaten-Auswahl
 * (Karenz/Rechnungshoheit/Mahnsperre/Höchststufe), Sammelmahnung mit
 * Stufen-Defaults + Mailversand, deterministische Verzugszins-Berechnung
 * (act/365) und Mahnsperre-Umschaltung inkl. Audit.
 */
final class DunningRunTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'country' => 'DE',
            'email' => 'buchhaltung@kunde.example',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function overdueInvoice(string $number, int $daysOverdue, array $overrides = []): Invoice {
        return Invoice::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'total' => '119.00',
            'issued_on' => now()->subDays($daysOverdue + 20),
            'due_on' => now()->subDays($daysOverdue),
        ], $overrides));
    }

    /** @param array<string, mixed> $dunning */
    private function configureDunning(array $dunning): void {
        $settings = (array) ($this->org->settings ?? []);
        $settings['invoicing']['dunning'] = $dunning;
        $this->org->update(['settings' => $settings]);
    }

    public function test_cockpit_partitions_candidates_waiting_blocked_and_excludes_external_and_maxed(): void {
        $this->configureDunning(['level1' => ['grace_days' => 7]]);

        $candidate = $this->overdueInvoice('R2026-8001', 10);
        $waiting = $this->overdueInvoice('R2026-8002', 3); // Karenz (7) nicht erreicht
        $blocked = $this->overdueInvoice('R2026-8003', 10, ['dunning_blocked_at' => now()]);
        $maxed = $this->overdueInvoice('R2026-8004', 10, ['dunning_level' => 3, 'dunned_at' => now()->subDays(30)]);

        // Externer Kunde (Rechnungshoheit): erscheint NIE im Mahnlauf.
        $externalCustomer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice,
        ]);
        $this->overdueInvoice('R2026-8005', 10, ['customer_id' => $externalCustomer->id]);

        $response = $this->actingAs($this->user)->get(route('finance.dunning.index'));
        $response->assertOk();
        $response->assertSee($candidate->number);
        $response->assertSee($waiting->number);   // Abschnitt „Karenz läuft"
        $response->assertSee($blocked->number);   // Abschnitt „Mahnsperren"
        $response->assertDontSee($maxed->number);
        $response->assertDontSee('R2026-8005');
    }

    public function test_grace_after_last_dunning_gates_level_two(): void {
        $this->configureDunning(['level2' => ['grace_days' => 7]]);

        $ready = $this->overdueInvoice('R2026-8010', 40, ['dunning_level' => 1, 'dunned_at' => now()->subDays(10)]);
        $waiting = $this->overdueInvoice('R2026-8011', 40, ['dunning_level' => 1, 'dunned_at' => now()->subDays(2)]);

        $service = app(DunningService::class);
        $this->assertTrue($service->isReadyForNextStep($ready));
        $this->assertFalse($service->isReadyForNextStep($waiting));
    }

    public function test_bulk_run_increases_levels_applies_step_defaults_and_sends_mail(): void {
        Mail::fake();
        $this->configureDunning([
            'level1' => ['grace_days' => 0, 'fee' => '5.50', 'pay_days' => 12],
        ]);

        $a = $this->overdueInvoice('R2026-8020', 10);
        $b = $this->overdueInvoice('R2026-8021', 15);

        $this->actingAs($this->user)
            ->post(route('finance.dunning.run'), ['ids' => [$a->sqid, $b->sqid]])
            ->assertRedirect(route('finance.dunning.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        foreach ([$a, $b] as $invoice) {
            $invoice->refresh();
            $this->assertSame(1, (int) $invoice->dunning_level);
            $this->assertNotNull($invoice->dunned_at);
        }

        // Stufen-Defaults der Org-Konfiguration: Gebühr 5,50 €, Frist +12 Tage.
        $expectedPayUntil = CarbonImmutable::today()->addDays(12)->toDateString();
        Mail::assertQueued(DunningMail::class, 2);
        Mail::assertQueued(DunningMail::class, function (DunningMail $mail) use ($expectedPayUntil): bool {
            return $mail->level === 1
                && $mail->fee === 5.5
                && $mail->payUntil?->toDateString() === $expectedPayUntil
                && $mail->hasTo('buchhaltung@kunde.example');
        });

        $this->assertSame(2, DocumentDispatch::query()->where('meta->kind', 'dunning')->count());
        $this->assertSame(5.5, (float) data_get(DocumentDispatch::query()->firstOrFail()->meta, 'fee'));
    }

    public function test_bulk_run_skips_blocked_and_collects_errors(): void {
        Mail::fake();
        $this->configureDunning(['level1' => ['grace_days' => 0]]);

        $ok = $this->overdueInvoice('R2026-8030', 10);
        $blocked = $this->overdueInvoice('R2026-8031', 10, ['dunning_blocked_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('finance.dunning.run'), ['ids' => [$ok->sqid, $blocked->sqid]])
            ->assertRedirect(route('finance.dunning.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('error', fn (string $msg): bool => str_contains($msg, 'R2026-8031'));

        $this->assertSame(1, (int) $ok->refresh()->dunning_level);
        $this->assertSame(0, (int) $blocked->refresh()->dunning_level);
        Mail::assertQueued(DunningMail::class, 1);
    }

    public function test_interest_is_deterministic_act_365(): void {
        $this->travelTo(CarbonImmutable::parse('2026-08-24 12:00:00'));
        $this->configureDunning(['interest_rate' => '7.3']);

        $invoice = $this->overdueInvoice('R2026-8040', 100, ['total' => '1000.00', 'tax_rate' => '0.00']);

        // 1000,00 × 100 Tage × 7,3 % / 365 = 20,00 (act/365, HalfUp).
        $interest = app(DunningService::class)->interest($invoice);
        $this->assertNotNull($interest);
        $this->assertSame(7.3, $interest['rate']);
        $this->assertSame(100, $interest['days']);
        $this->assertSame(20.0, $interest['amount']);

        // Ausweis wandert in Audit + Dispatch-Meta der Mahnung.
        Mail::fake();
        $this->actingAs($this->user)
            ->post(route('invoices.dun', $invoice), ['send_mail' => '1', 'email' => 'debitor@kunde.example'])
            ->assertRedirect(route('invoices.show', $invoice));
        $audit = AuditLog::query()->where('event', 'invoice.dunned')->latest('id')->firstOrFail();
        $this->assertSame(20.0, (float) data_get($audit->changes, 'interest.amount'));
        Mail::assertQueued(DunningMail::class, fn (DunningMail $mail): bool => ($mail->interest['amount'] ?? null) === 20.0);

        $this->travelBack();
    }

    public function test_interest_zero_rate_yields_no_interest(): void {
        $invoice = $this->overdueInvoice('R2026-8041', 50);
        $this->assertNull(app(DunningService::class)->interest($invoice));
    }

    public function test_dunning_block_toggle_audits_and_blocks_single_dun(): void {
        $invoice = $this->overdueInvoice('R2026-8050', 10);

        $this->actingAs($this->user)
            ->from(route('invoices.show', $invoice))
            ->post(route('invoices.dunning-block', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertNotNull($invoice->dunning_blocked_at);
        $this->assertSame(1, AuditLog::query()->where('event', 'invoice.dunningBlocked')->count());

        // Gesperrt: Einzelmahnung (Dialog + POST) ist zu.
        $this->actingAs($this->user)->get(route('invoices.dun.form', $invoice))->assertStatus(422);
        $this->actingAs($this->user)
            ->post(route('invoices.dun', $invoice))
            ->assertSessionHas('error');
        $this->assertSame(0, (int) $invoice->refresh()->dunning_level);

        // Aufheben: Audit + Feld leer.
        $this->actingAs($this->user)
            ->post(route('invoices.dunning-block', $invoice))
            ->assertSessionHas('status');
        $this->assertNull($invoice->refresh()->dunning_blocked_at);
        $this->assertSame(1, AuditLog::query()->where('event', 'invoice.dunningUnblocked')->count());
    }

    public function test_run_requires_billing_permission(): void {
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)->get(route('finance.dunning.index'))->assertForbidden();
        $this->actingAs($member)->post(route('finance.dunning.run'), ['ids' => ['x']])->assertForbidden();
    }
}
