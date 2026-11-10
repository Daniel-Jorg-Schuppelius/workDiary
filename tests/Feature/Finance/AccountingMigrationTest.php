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

use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Finance\{BillingMode, TransferTarget};
use App\Enums\Migration\AccountingMigrationItemStatus;
use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Migration\{AccountingMigrationEvent, AccountingMigrationItem, AccountingMigrationRun};
use App\Models\Platform\{Organization, User};
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\OrgaMax\Enums\OrgaMaxInvoiceStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
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
        $this->assertSame(AccountingMigrationItemStatus::Matched, $matchedItem->status);
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
        $this->service()->decideItem($item, AccountingMigrationItemStatus::Skipped, $this->admin, 'Karteileiche');

        $this->service()->analyze($run->refresh(), $this->admin);

        $this->assertSame(1, $run->items()->count(), 'Wiederholung erzeugt keine Dubletten.');
        $this->assertSame(AccountingMigrationItemStatus::Skipped, $item->refresh()->status, 'Getroffene Entscheidungen bleiben erhalten.');
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
        $this->assertSame(AccountingMigrationItemStatus::Historic, $item->status, 'Belege bleiben Historie und werden nie nachgebaut.');
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
        $this->service()->decideItem($item, AccountingMigrationItemStatus::Conflict, $this->admin, 'Mehrdeutig');

        $blockers = $this->service()->cutover($run->refresh(), $this->admin);
        $this->assertNotSame([], $blockers);
        $this->assertSame(AccountingMigrationStatus::Blocked, $run->refresh()->status);
        $this->assertNull($customer->refresh()->billing_cutover_on, 'Ohne saubere Zuordnung kein Stichtag.');

        // Nach der Entscheidung läuft die Umschaltung durch.
        $this->service()->decideItem($item->refresh(), AccountingMigrationItemStatus::Matched, $this->admin);
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
        $this->service()->decideItem($item, AccountingMigrationItemStatus::Matched, $this->admin);
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
        $this->assertSame(AccountingMigrationItemStatus::Matched, $item->status);

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
            'invoice_status' => OrgaMaxInvoiceStatus::Locked,
            'invoice_date' => '2026-07-01',
            'outstanding_amount' => 119.0,
        ]);
        OrgaMaxInvoice::query()->create([
            'organization_id' => $this->org->id,
            'external_id' => 'inv-2',
            'invoice_number' => 'INV-2',
            'invoice_status' => OrgaMaxInvoiceStatus::Paid,
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
        $this->assertSame(2, $run->items()->where('status', AccountingMigrationItemStatus::Historic)->count());
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

    /** Die Positionsliste blättert, Konflikte zuerst — früher endete sie still nach 200 Positionen. */
    public function test_item_list_pages_with_conflicts_first(): void {
        $run = $this->plan();
        AccountingMigrationItem::factory()->count(200)->create([
            'organization_id' => $this->org->id,
            'accounting_migration_run_id' => $run->id,
            'status' => AccountingMigrationItemStatus::Matched,
        ]);
        $pending = AccountingMigrationItem::factory()->create([
            'organization_id' => $this->org->id,
            'accounting_migration_run_id' => $run->id,
            'status' => AccountingMigrationItemStatus::Pending,
            'display_title' => 'Offene Position',
        ]);
        $conflict = AccountingMigrationItem::factory()->create([
            'organization_id' => $this->org->id,
            'accounting_migration_run_id' => $run->id,
            'status' => AccountingMigrationItemStatus::Conflict,
            'display_title' => 'Strittige Position',
        ]);

        $first = $this->actingAs($this->admin)->get(route('admin.accounting-migration.index'))->assertOk();
        $items = $first->viewData('items');
        $this->assertSame(202, $items->total());
        $this->assertCount(50, $items->items());
        $this->assertSame([$conflict->id, $pending->id], [$items->items()[0]->id, $items->items()[1]->id]);

        $last = $this->actingAs($this->admin)->get(route('admin.accounting-migration.index', ['page' => 5]))->assertOk();
        $this->assertCount(2, $last->viewData('items')->items());
        $last->assertSee(route('admin.accounting-migration.decide', [$run->sqid, $last->viewData('items')->items()[1]->sqid]));
    }

    /** Positionsliste, Entscheidung und Protokoll lesen den Stand als Enum (Label in der Liste, Speicherwert im CSV). */
    public function test_item_status_is_shown_decided_and_exported(): void {
        $this->customerWithRefs('Nur Quelle', 'lex-2');
        $run = $this->plan();
        $this->service()->analyze($run->refresh(), $this->admin);
        $item = $run->items()->firstOrFail();
        $this->assertSame(AccountingMigrationItemStatus::Pending, $item->status);

        $this->actingAs($this->admin)->get(route('admin.accounting-migration.index'))
            ->assertOk()
            ->assertSeeText(AccountingMigrationItemStatus::Pending->label());

        $decide = route('admin.accounting-migration.decide', [$run->sqid, $item->sqid]);
        // „übertragen" setzt nur ein Schreiblauf, nie eine Person.
        $this->actingAs($this->admin)->post($decide, ['status' => 'transferred'])->assertSessionHasErrors('status');
        $this->assertSame(AccountingMigrationItemStatus::Pending, $item->refresh()->status);

        $this->actingAs($this->admin)->post($decide, ['status' => 'conflict', 'note' => 'Mehrdeutig'])->assertSessionHasNoErrors();
        $this->assertSame(AccountingMigrationItemStatus::Conflict, $item->refresh()->status);
        $this->assertTrue($item->blocksCutover());
        $this->assertSame('conflict', AccountingMigrationEvent::query()->where('event', 'item_decided')->latest('id')->firstOrFail()->payload['status']);

        $csv = $this->actingAs($this->admin)->get(route('admin.accounting-migration.report', $run->sqid))->assertOk()->streamedContent();
        $this->assertStringContainsString(';conflict;lex-2;', $csv);
    }

    public function test_sevdesk_to_easybill_reads_the_shared_mirror_and_matches_contacts(): void {
        // Feature 110, MVP-1506: sevDesk und easybill gleichen über die Kontaktverknüpfung und den Belegspiegel ab.
        $both = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Bäckerei Morgenrot']);
        $sourceOnly = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Gärtnerei Lindenhof']);
        foreach ([[$both, 'sevdesk', 'sd-1'], [$both, 'easybill', 'eb-1'], [$sourceOnly, 'sevdesk', 'sd-2']] as [$customer, $plugin, $id]) {
            ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => $plugin, 'external_type' => 'contact',
                'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(), 'external_id' => $id]);
        }
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => 'sevdesk', 'external_id' => 'invoice:1',
            'voucher_number' => 'RE-SD-1', 'voucher_state' => AccountingVoucherState::Open, 'open_amount' => '119.00']);
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => 'sevdesk', 'external_id' => 'invoice:2',
            'voucher_number' => 'RE-SD-2', 'voucher_state' => AccountingVoucherState::Paid, 'open_amount' => '0.00']);
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => 'sevdesk', 'external_id' => 'invoice:3',
            'voucher_number' => 'Wartungsvertrag', 'voucher_state' => AccountingVoucherState::Draft, 'document_kind' => 'recurring_template']);
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => 'sevdesk', 'external_id' => 'invoice:4',
            'voucher_number' => 'RE-SD-4', 'voucher_state' => AccountingVoucherState::Open, 'source_deleted_at' => now()]);
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => 'easybill', 'external_id' => '901',
            'voucher_state' => AccountingVoucherState::Open]);

        $run = $this->plan([MigrationDataArea::Customers, MigrationDataArea::Documents], '2026-09-01', MigrationProvider::SevDesk, MigrationProvider::Easybill);
        $this->service()->analyze($run->refresh(), $this->admin);

        $counters = $run->refresh()->counters;
        $this->assertSame(['read' => 2, 'matched' => 1, 'pending' => 1, 'conflict' => 0], $counters['customers']);
        $this->assertSame(['read' => 3, 'historic' => 3, 'open' => 1], $counters['documents'], 'Serienvorlage zählt nicht, gelöschte Belege sind nicht offen, easybill-Belege gehören nicht zur Quelle.');
        $this->assertSame('eb-1', $run->items()->where('source_external_id', 'sd-1')->value('target_external_id'));
        $this->assertSame(1, $run->items()->where('status', AccountingMigrationItemStatus::Pending)->count());
        $this->assertSame(['RE-SD-1', 'RE-SD-2', 'RE-SD-4'], $run->items()->where('data_area', 'documents')->orderBy('id')->pluck('display_title')->all());
        $this->assertStringContainsString('1 offene Altbelege', implode(' ', $this->service()->completionBlockers($run->refresh())));
    }

    public function test_the_cutover_switches_to_easybill_and_locks_sevdesk(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $run = $this->plan([MigrationDataArea::Customers], now()->toDateString(), MigrationProvider::SevDesk, MigrationProvider::Easybill);
        $this->service()->analyze($run->refresh(), $this->admin);

        $this->assertSame([], $this->service()->startParallelRun($run->refresh(), $this->admin));
        $this->assertSame([], $this->service()->cutover($run->refresh(), $this->admin));

        $this->assertSame(BillingMode::Easybill->value, $this->org->refresh()->settings['billing_mode'] ?? null);
        $guard = app(CutoverGuard::class);
        $customer->refresh();
        $this->assertSame(TransferTarget::SevDesk, $guard->blockedTarget($customer));
        $this->assertFalse($guard->allowsTarget($customer, TransferTarget::SevDesk));
        $this->assertTrue($guard->allowsTarget($customer, TransferTarget::Easybill));
    }

    public function test_articles_cannot_be_reconciled_with_sevdesk_or_easybill(): void {
        try {
            $this->plan([MigrationDataArea::Customers, MigrationDataArea::Articles], '2026-09-01', MigrationProvider::Lexoffice, MigrationProvider::SevDesk);
            $this->fail('Artikel lassen sich mit sevDesk nicht abgleichen.');
        } catch (RuntimeException $e) {
            $this->assertSame('Den Bereich Artikel und Leistungen gleicht der Wechsel mit sevDesk nicht ab. Wählen Sie ihn ab.', $e->getMessage());
        }
        $this->assertNull($this->service()->openRunFor($this->org));

        $this->actingAs($this->admin)->post(route('admin.accounting-migration.store'), [
            'areas' => ['customers', 'documents'],
            'cutover_on' => now()->addMonth()->toDateString(),
            'source' => 'easybill',
            'target' => 'sevdesk',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $run = $this->service()->openRunFor($this->org);
        $this->assertSame([MigrationProvider::Easybill, MigrationProvider::SevDesk], [$run?->source(), $run?->target()]);

        $this->actingAs($this->admin)->post(route('admin.accounting-migration.store'), ['areas' => ['customers'], 'source' => 'billbee', 'target' => 'sevdesk'])
            ->assertSessionHasErrors('source');
    }

    /** MVP-1528: Ein abgelehnter Plan behält Quelle, Ziel und Stichtag und wählt nicht abgleichbare Bereiche ab. */
    public function test_failed_plan_keeps_the_input_and_deselects_unsupported_areas(): void {
        $cutover = now()->addMonth()->toDateString();

        $this->actingAs($this->admin)->from(route('admin.accounting-migration.index'))
            ->post(route('admin.accounting-migration.store'), [
                'areas' => ['customers', 'articles', 'documents'],
                'cutover_on' => $cutover,
                'source' => 'sevdesk',
                'target' => 'easybill',
            ])
            ->assertRedirect(route('admin.accounting-migration.index'))
            ->assertSessionHas('error', 'Den Bereich Artikel und Leistungen gleicht der Wechsel mit sevDesk nicht ab. Wählen Sie ihn ab. Im Formular ist er bereits abgewählt; prüfen Sie die Auswahl und planen Sie erneut.')
            ->assertSessionHasInput('source', 'sevdesk')
            ->assertSessionHasInput('target', 'easybill')
            ->assertSessionHasInput('cutover_on', $cutover)
            ->assertSessionHasInput('areas', ['customers', 'documents']);
        $this->assertNull($this->service()->openRunFor($this->org));

        $this->actingAs($this->admin)->get(route('admin.accounting-migration.index'))->assertOk()
            ->assertSee('<option value="sevdesk" selected>', false)
            ->assertSee('<option value="easybill" selected>', false)
            ->assertSee('value="customers" class="checkbox checkbox-sm" checked', false)
            ->assertDontSee('value="articles" class="checkbox checkbox-sm" checked', false)
            ->assertSee('value="' . $cutover . '"', false)
            ->assertSee('nicht mit sevDesk, easybill');
    }

    public function test_plan_form_preselects_lexoffice_to_orgamax_with_all_areas(): void {
        $this->actingAs($this->admin)->get(route('admin.accounting-migration.index'))->assertOk()
            ->assertSee('<option value="lexoffice" selected>', false)
            ->assertSee('<option value="orgamax" selected>', false)
            ->assertSee('value="articles" class="checkbox checkbox-sm" checked', false);
    }
}
