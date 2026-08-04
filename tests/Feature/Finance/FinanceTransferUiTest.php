<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceTransferUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Organization, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Services\Finance\BillingTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * UI-Schicht der Faktura-Übergabe (Feature 045, Teil B): Permissions auf
 * index/show/create, Draft-Anlage über store, Ziel-Vorbelegung nach
 * billing_mode, void-Regeln und Mandantentrennung (Cross-Org 404).
 */
class FinanceTransferUiTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->accountant->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);
    }

    private function makeTimeEntry(array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ], $overrides));
    }

    private function makeDraft(TransferTarget $target = TransferTarget::File): BillingTransfer {
        $this->makeTimeEntry();

        return app(BillingTransferService::class)->createDraft(
            $this->customer,
            TransferChannel::Time,
            $target,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
    }

    // ── Permissions ─────────────────────────────────────────────────────

    public function test_index_requires_finance_view_any(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('finance.transfers.index'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('finance.transfers.index'))->assertOk();
    }

    public function test_show_requires_finance_view_any(): void {
        $transfer = $this->makeDraft();

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('finance.transfers.show', $transfer))->assertForbidden();

        $this->actingAs($this->accountant)
            ->get(route('finance.transfers.show', $transfer))
            ->assertOk()
            ->assertSee('ACME')
            ->assertSee($transfer->payload_hash);
    }

    public function test_create_dialog_requires_channel_permission(): void {
        // geschaeftsfuehrung: nur finance.viewAny, KEINE Kanal-Permission.
        $exec = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($exec)->get(route('finance.transfers.create'))->assertForbidden();

        $this->actingAs($this->accountant)->get(route('finance.transfers.create'))->assertOk();
    }

    public function test_store_requires_channel_permission(): void {
        $exec = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($exec)->post(route('finance.transfers.store'), [
            'customer_id' => $this->customer->sqid,
            'channel' => TransferChannel::Time->value,
            'target' => TransferTarget::File->value,
        ])->assertForbidden();
    }

    // ── Anlage ──────────────────────────────────────────────────────────

    public function test_store_creates_draft_with_items(): void {
        $entry = $this->makeTimeEntry();

        $response = $this->actingAs($this->accountant)->post(route('finance.transfers.store'), [
            'customer_id' => $this->customer->sqid,
            'channel' => TransferChannel::Time->value,
            'target' => TransferTarget::File->value,
            'from' => '2030-04-01',
            'to' => '2030-04-30',
        ]);

        $transfer = BillingTransfer::query()->firstOrFail();
        $response->assertRedirect(route('finance.transfers.show', $transfer));

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertSame(TransferChannel::Time, $transfer->channel);
        $this->assertSame(TransferTarget::File, $transfer->target);
        $this->assertSame(1, $transfer->items()->count());
        $this->assertSame((int) $entry->id, (int) $transfer->items()->first()->source_id);
        $this->assertSame(TimeEntry::class, $transfer->items()->first()->source_type);
        // Draft verbraucht die Quelle noch nicht.
        $this->assertFalse((bool) $entry->fresh()->exported);
    }

    public function test_store_without_sources_returns_error(): void {
        $this->actingAs($this->accountant)->post(route('finance.transfers.store'), [
            'customer_id' => $this->customer->sqid,
            'channel' => TransferChannel::Time->value,
            'target' => TransferTarget::File->value,
        ])->assertSessionHasErrors('from');

        $this->assertSame(0, BillingTransfer::query()->count());
    }

    // ── Ziel-Vorbelegung nach billing_mode ─────────────────────────────

    public function test_create_preselects_lexoffice_target_for_lexoffice_customer(): void {
        $this->customer->update(['billing_mode' => BillingMode::Lexoffice]);

        $response = $this->actingAs($this->accountant)
            ->get(route('finance.transfers.create', ['customer' => $this->customer->sqid]));

        $response->assertOk()
            ->assertSee('value="lexoffice" selected', false)
            ->assertSee('value="file"', false);
    }

    public function test_create_preselects_file_target_with_hint_for_datev_customer(): void {
        $this->customer->update(['billing_mode' => BillingMode::Datev]);

        $response = $this->actingAs($this->accountant)
            ->get(route('finance.transfers.create', ['customer' => $this->customer->sqid]));

        $response->assertOk()
            ->assertSee('value="file" selected', false)
            ->assertDontSee('value="lexoffice"', false)
            ->assertSee(__('finance.hint.datev_desktop_api'));
    }

    public function test_create_offers_only_file_target_for_workdiary_customer(): void {
        $response = $this->actingAs($this->accountant)
            ->get(route('finance.transfers.create', ['customer' => $this->customer->sqid]));

        $response->assertOk()
            ->assertSee('value="file" selected', false)
            ->assertDontSee('value="lexoffice"', false);
    }

    public function test_store_rejects_lexoffice_target_for_workdiary_customer(): void {
        $this->makeTimeEntry();

        $this->actingAs($this->accountant)->post(route('finance.transfers.store'), [
            'customer_id' => $this->customer->sqid,
            'channel' => TransferChannel::Time->value,
            'target' => TransferTarget::Lexoffice->value,
        ])->assertSessionHasErrors('target');

        $this->assertSame(0, BillingTransfer::query()->count());
    }

    // ── void ────────────────────────────────────────────────────────────

    public function test_void_after_draft_releases_transfer(): void {
        $transfer = $this->makeDraft();

        $this->actingAs($this->accountant)
            ->post(route('finance.transfers.void', $transfer))
            ->assertRedirect(route('finance.transfers.index'));

        $this->assertSame(TransferStatus::Voided, $transfer->fresh()->status);
    }

    public function test_void_after_transferred_is_rejected(): void {
        $transfer = $this->makeDraft();
        $service = app(BillingTransferService::class);
        $service->confirm($transfer, $this->accountant);
        $service->markTransferred($transfer->fresh(), filePath: 'exports/finance/x.csv', actor: $this->accountant);

        $this->actingAs($this->accountant)
            ->from(route('finance.transfers.show', $transfer))
            ->post(route('finance.transfers.void', $transfer))
            ->assertSessionHasErrors('status');

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
    }

    // ── Storno (cancel) ─────────────────────────────────────────────────

    public function test_cancel_requires_finance_config(): void {
        $transfer = $this->makeDraft();
        $service = app(BillingTransferService::class);
        $service->confirm($transfer, $this->accountant);
        $service->markTransferred($transfer->fresh(), filePath: 'exports/finance/x.csv', actor: $this->accountant);

        // Buchhaltung ohne finance.config: kein Storno.
        $this->actingAs($this->accountant)
            ->post(route('finance.transfers.cancel', $transfer))
            ->assertForbidden();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
    }

    public function test_cancel_releases_sources_after_transfer(): void {
        $transfer = $this->makeDraft();
        $service = app(BillingTransferService::class);
        $service->confirm($transfer, $this->accountant);
        $service->markTransferred($transfer->fresh(), filePath: 'exports/finance/x.csv', actor: $this->accountant);

        $entryId = (int) $transfer->items()->firstOrFail()->source_id;
        $this->assertTrue((bool) TimeEntry::query()->findOrFail($entryId)->exported);

        $this->grantPermissions($this->accountant, [\App\Enums\User\Permission::FinanceConfig]);

        $this->actingAs($this->accountant->fresh())
            ->from(route('finance.transfers.show', $transfer))
            ->post(route('finance.transfers.cancel', $transfer), ['reason' => 'Beleg im Ziel verworfen'])
            ->assertRedirect(route('finance.transfers.show', $transfer));

        $this->assertSame(TransferStatus::Cancelled, $transfer->fresh()->status);
        $this->assertFalse((bool) TimeEntry::query()->findOrFail($entryId)->exported);
    }

    // ── Mandantentrennung ───────────────────────────────────────────────

    public function test_cross_org_transfer_returns_404(): void {
        $transfer = $this->makeDraft();
        $sqid = $transfer->sqid;

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $foreign = User::factory()->buchhaltung()->create(['organization_id' => $orgB->id]);

        $this->actingAs($foreign)
            ->get(route('finance.transfers.show', ['transfer' => $sqid]))
            ->assertNotFound();
    }
}
