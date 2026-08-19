<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferTarget};
use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Models\{Customer, ExternalReference, LexofficeVoucher, OrgaMaxInvoice, Organization, User};
use App\Models\Migration\{AccountingMigrationEvent, AccountingMigrationItem, AccountingMigrationRun};
use App\Services\AccountingMigration\{AccountingMigrationService, CutoverGuard};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * MVP-653 (Issue #86): kontrollierter Buchhaltungswechsel Lexoffice →
 * orgaMAX. Prüft die verbindlichen Grundsätze: Dry-Run ohne Fremdsystem-
 * Schreibzugriff, beide Provider-IDs am selben lokalen Objekt, keine
 * Nachbildung finalisierter Belege, Stichtagssperre gegen Doppel-
 * Fakturierung, Blockade der Umschaltung bei Konflikten und die Auditspur.
 */
class AccountingMigrationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function service(): AccountingMigrationService {
        return app(AccountingMigrationService::class);
    }

    /** Kunde mit Lexoffice-Referenz; optional zusätzlich orgaMAX-Referenz. */
    private function customerWithRefs(string $name, string $lexId, ?string $orgaId = null): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->org->id,
            'plugin_id' => 'lexoffice',
            'external_type' => 'contact',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => $lexId,
        ]);
        if ($orgaId !== null) {
            ExternalReference::create([
                'organization_id' => $this->org->id,
                'plugin_id' => 'orgamax',
                'external_type' => 'customer',
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
                'external_id' => $orgaId,
            ]);
        }

        return $customer;
    }

    private function plan(
        array $areas = [MigrationDataArea::Customers],
        ?string $cutover = '2026-09-01',
        MigrationProvider $source = MigrationProvider::Lexoffice,
        MigrationProvider $target = MigrationProvider::OrgaMax,
    ): AccountingMigrationRun {
        return $this->service()->plan(
            $this->org,
            $areas,
            $cutover !== null ? CarbonImmutable::parse($cutover) : null,
            $this->admin,
            $source,
            $target,
        );
    }

    public function test_dry_run_maps_both_provider_ids_without_touching_foreign_systems(): void {
        $mapped = $this->customerWithRefs('Beide Systeme', 'lex-1', 'orga-1');
        $this->customerWithRefs('Nur Quelle', 'lex-2');

        $run = $this->plan();
        $this->service()->analyze($run->refresh(), $this->admin);

        $run->refresh();
        $this->assertSame(2, $run->counters['customers']['read'] ?? 0);
        $this->assertSame(1, $run->counters['customers']['matched'] ?? 0);
        $this->assertSame(1, $run->counters['customers']['pending'] ?? 0);

        $matchedItem = $run->items()->where('source_external_id', 'lex-1')->firstOrFail();
        $this->assertSame(AccountingMigrationItem::STATUS_MATCHED, $matchedItem->status);
        $this->assertSame('orga-1', $matchedItem->target_external_id);
        $this->assertSame((int) $mapped->id, (int) $matchedItem->referenceable_id, 'Beide Provider-IDs zeigen auf dasselbe lokale Objekt.');

        // Der Dry-Run legt keine neuen Fremd-Referenzen an.
        $this->assertSame(1, ExternalReference::query()->where('plugin_id', 'orgamax')->count());
        $this->assertTrue($run->dry_run_only);
        $this->assertSame(AccountingMigrationStatus::Mapping, $run->status);
    }

    public function test_repeated_analysis_is_idempotent_and_keeps_decisions(): void {
        $this->customerWithRefs('Kunde', 'lex-1');
        $run = $this->plan();
        $this->service()->analyze($run->refresh(), $this->admin);

        $item = $run->items()->firstOrFail();
        $this->service()->decideItem($item, AccountingMigrationItem::STATUS_SKIPPED, $this->admin, 'Karteileiche');

        $this->service()->analyze($run->refresh(), $this->admin);

        $this->assertSame(1, $run->items()->count(), 'Wiederholung erzeugt keine Dubletten.');
        $this->assertSame(AccountingMigrationItem::STATUS_SKIPPED, $item->refresh()->status, 'Getroffene Entscheidungen bleiben erhalten.');
    }

    public function test_finalized_documents_stay_historic_and_open_ones_block_completion(): void {
        LexofficeVoucher::create([
            'organization_id' => $this->org->id,
            'external_id' => 'v-1',
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'open',
            'voucher_number' => 'RE-1',
            'voucher_date' => now()->subMonth()->toDateString(),
            'archived' => false,
        ]);

        $run = $this->plan([MigrationDataArea::Documents]);
        $this->service()->analyze($run->refresh(), $this->admin);

        $item = $run->items()->firstOrFail();
        $this->assertSame(AccountingMigrationItem::STATUS_HISTORIC, $item->status, 'Belege bleiben Historie und werden nie nachgebaut.');
        $this->assertSame(1, $run->refresh()->counters['documents']['open'] ?? 0);

        // Offener Altbeleg blockiert den Abschluss.
        $blockers = $this->service()->completionBlockers($run->refresh());
        $this->assertNotSame([], $blockers);
        $this->assertStringContainsString('Altbelege', implode(' ', $blockers));
    }

    public function test_conflicts_block_cutover_until_decided(): void {
        $customer = $this->customerWithRefs('Kunde', 'lex-1');
        $run = $this->plan();
        $this->service()->analyze($run->refresh(), $this->admin);

        $item = $run->items()->firstOrFail();
        $this->service()->decideItem($item, AccountingMigrationItem::STATUS_CONFLICT, $this->admin, 'Mehrdeutig');

        $blockers = $this->service()->cutover($run->refresh(), $this->admin);
        $this->assertNotSame([], $blockers);
        $this->assertSame(AccountingMigrationStatus::Blocked, $run->refresh()->status);
        $this->assertNull($customer->refresh()->billing_cutover_on, 'Ohne saubere Zuordnung kein Stichtag.');

        // Nach der Entscheidung läuft die Umschaltung durch.
        $this->service()->decideItem($item->refresh(), AccountingMigrationItem::STATUS_MATCHED, $this->admin);
        $this->service()->transition($run->refresh(), AccountingMigrationStatus::Mapping, $this->admin);
        $this->assertSame([], $this->service()->startParallelRun($run->refresh(), $this->admin));
        $this->assertSame([], $this->service()->cutover($run->refresh(), $this->admin));

        $this->assertSame(AccountingMigrationStatus::Verifying, $run->refresh()->status);
        $this->assertSame('2026-09-01', $customer->refresh()->billing_cutover_on?->toDateString());
        $this->assertSame(BillingMode::OrgaMax->value, data_get($this->org->refresh()->settings, 'billing_mode'));
    }

    public function test_cutover_guard_blocks_source_target_after_cutover_date(): void {
        $guard = app(CutoverGuard::class);
        $customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'billing_cutover_on' => now()->subDay()->toDateString(),
            'billing_cutover_from' => MigrationProvider::Lexoffice->value,
        ]);

        $this->assertTrue($guard->isCutoverReached($customer));
        $this->assertFalse($guard->allowsTarget($customer, TransferTarget::Lexoffice), 'Quellsystem ist nach dem Stichtag gesperrt.');
        $this->assertTrue($guard->allowsTarget($customer, TransferTarget::OrgaMax));
        $this->assertTrue($guard->allowsTarget($customer, TransferTarget::File));
        $this->assertSame([TransferTarget::OrgaMax], $guard->filterTargets($customer, [TransferTarget::Lexoffice, TransferTarget::OrgaMax]));
        $this->assertNotNull($guard->blockReason($customer, TransferTarget::Lexoffice));

        // Vor dem Stichtag bleibt alles offen.
        $future = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'billing_cutover_on' => now()->addWeek()->toDateString(),
            'billing_cutover_from' => MigrationProvider::Lexoffice->value,
        ]);
        $this->assertTrue($guard->allowsTarget($future, TransferTarget::Lexoffice));

        // Ohne Wechsel (kein Stichtag) ändert sich nichts.
        $untouched = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->assertFalse($guard->isCutoverReached($untouched));
        $this->assertTrue($guard->allowsTarget($untouched, TransferTarget::Lexoffice));
    }

    public function test_only_one_open_run_per_organization(): void {
        $this->plan();

        $this->expectException(RuntimeException::class);
        $this->plan();
    }

    public function test_every_step_is_recorded_in_the_audit_chain(): void {
        $this->customerWithRefs('Kunde', 'lex-1');
        $run = $this->plan();
        $this->service()->analyze($run->refresh(), $this->admin);
        $item = $run->items()->firstOrFail();
        $this->service()->decideItem($item, AccountingMigrationItem::STATUS_MATCHED, $this->admin);
        $this->service()->startParallelRun($run->refresh(), $this->admin);
        $this->service()->cutover($run->refresh(), $this->admin);
        $this->service()->complete($run->refresh(), $this->admin);

        $events = AccountingMigrationEvent::query()
            ->where('accounting_migration_run_id', $run->id)
            ->orderBy('id')
            ->pluck('event')
            ->all();
        foreach (['planned', 'analyzed', 'item_decided', 'parallel_run_started', 'cutover_executed', 'completed'] as $expected) {
            $this->assertContains($expected, $events);
        }

        // Hash-Kette: jeder Eintrag verweist auf seinen Vorgänger.
        $chain = AccountingMigrationEvent::query()->orderBy('id')->get();
        $this->assertNull($chain->first()?->prev_hash);
        $this->assertNotNull($chain->last()?->hash);
        $this->assertSame($chain[0]->hash, $chain[1]->prev_hash);

        $this->assertSame(AccountingMigrationStatus::Completed, $run->refresh()->status);
        $this->assertNotNull($run->completed_at);
    }

    public function test_migration_runs_in_the_reverse_direction_too(): void {
        // orgaMAX → Lexoffice: dieselbe Maschinerie, gespiegelte Fremd-Typen.
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Rückweg']);
        ExternalReference::create([
            'organization_id' => $this->org->id,
            'plugin_id' => 'orgamax',
            'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'orga-9',
        ]);
        ExternalReference::create([
            'organization_id' => $this->org->id,
            'plugin_id' => 'lexoffice',
            'external_type' => 'contact',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'lex-9',
        ]);

        $run = $this->plan(
            [MigrationDataArea::Customers],
            '2026-09-01',
            MigrationProvider::OrgaMax,
            MigrationProvider::Lexoffice,
        );
        $this->service()->analyze($run->refresh(), $this->admin);

        $item = $run->items()->firstOrFail();
        $this->assertSame('orga-9', $item->source_external_id, 'Quelle ist jetzt orgaMAX.');
        $this->assertSame('lex-9', $item->target_external_id, 'Ziel ist jetzt Lexoffice.');
        $this->assertSame(AccountingMigrationItem::STATUS_MATCHED, $item->status);

        $this->assertSame([], $this->service()->startParallelRun($run->refresh(), $this->admin));
        $this->assertSame([], $this->service()->cutover($run->refresh(), $this->admin));

        // Fakturahoheit und Sperre folgen der gewählten Richtung.
        $this->assertSame(BillingMode::Lexoffice->value, data_get($this->org->refresh()->settings, 'billing_mode'));
        $customer->refresh();
        $this->assertSame(MigrationProvider::OrgaMax->value, $customer->billing_cutover_from);

        $guard = app(CutoverGuard::class);
        $afterCutover = CarbonImmutable::parse('2026-09-02');
        $this->assertFalse($guard->allowsTarget($customer, TransferTarget::OrgaMax, $afterCutover), 'Jetzt ist orgaMAX das gesperrte Quellsystem.');
        $this->assertTrue($guard->allowsTarget($customer, TransferTarget::Lexoffice, $afterCutover), 'Lexoffice ist das neue Ziel und bleibt offen.');
    }

    public function test_reverse_direction_reads_document_history_from_the_orgamax_mirror(): void {
        // Gegenrichtung: orgaMAX ist Quelle, die Beleghistorie kommt aus dem
        // lokalen Spiegel `orgamax_invoices` (Feature-077-Fix) — jede Rechnung
        // ist ein eigenes Objekt, mehrere Belege sind also abbildbar.
        OrgaMaxInvoice::query()->create([
            'organization_id' => $this->org->id,
            'external_id' => 'inv-1',
            'invoice_number' => 'INV-1',
            'invoice_status' => 'open',
            'invoice_date' => '2026-07-01',
            'outstanding_amount' => 119.0,
        ]);
        OrgaMaxInvoice::query()->create([
            'organization_id' => $this->org->id,
            'external_id' => 'inv-2',
            'invoice_number' => 'INV-2',
            'invoice_status' => 'paid',
            'invoice_date' => '2026-06-01',
        ]);

        $run = $this->plan(
            [MigrationDataArea::Documents],
            '2026-09-01',
            MigrationProvider::OrgaMax,
            MigrationProvider::Lexoffice,
        );
        $this->service()->analyze($run->refresh(), $this->admin);

        $this->assertSame(2, $run->refresh()->counters['documents']['read'] ?? 0);
        $this->assertSame(1, $run->refresh()->counters['documents']['open'] ?? 0, 'Nur der offene Beleg zählt.');
        $this->assertSame(2, $run->items()->where('status', AccountingMigrationItem::STATUS_HISTORIC)->count());
        $this->assertSame('INV-1', $run->items()->orderBy('id')->firstOrFail()->display_title, 'Belegnummer kommt aus dem Spiegel.');

        $blockers = $this->service()->completionBlockers($run->refresh());
        $this->assertStringContainsString('Altbelege', implode(' ', $blockers));
    }

    public function test_source_and_target_must_differ(): void {
        $this->expectException(RuntimeException::class);
        $this->plan([MigrationDataArea::Customers], '2026-09-01', MigrationProvider::OrgaMax, MigrationProvider::OrgaMax);
    }

    public function test_admin_ui_is_reachable_and_denies_unprivileged_users(): void {
        $this->actingAs($this->admin)->get(route('admin.accounting-migration.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.accounting-migration.store'), [
            'areas' => ['customers', 'documents'],
            'cutover_on' => now()->addMonth()->toDateString(),
            'source' => 'lexoffice',
            'target' => 'orgamax',
        ])->assertRedirect();
        $this->assertNotNull($this->service()->openRunFor($this->org));

        $regular = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $this->actingAs($regular)->get(route('admin.accounting-migration.index'))->assertForbidden();
    }
}
