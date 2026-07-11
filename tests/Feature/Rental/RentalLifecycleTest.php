<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Rental;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\Rental\{RentalCaseStatus, RentalChargeKind, RentalChargeStatus, RentalDepositStatus, RentalRateCardStatus, RentalReturnFollowUp};
use App\Exceptions\{AssetNotUsableException, RentalConflictException};
use App\Models\{Asset, Customer, User};
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalReservation};
use App\Services\Asset\{AssetBlockService, AssetUsageGuard};
use App\Services\Rental\{RentalAvailabilityService, RentalBillingService, RentalCaseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 073 (MVP-258–269): Verleihakte mit Nummernkreis und
 * Konditionen-Snapshot (D10), Verfügbarkeit mit Puffern und Konflikten,
 * gemeinsames Sperrmodell mit Ausnahmefreigabe (D12), Übergabe/Rücknahme
 * mit Folgeentscheidungen (Sperre/Claims), Verlängerung/Tausch/Überfällig,
 * Faktura-Übergabe mit Beleghoheit und Kaution als eigener Finanzvorgang.
 */
final class RentalLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger']);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'is_rentable' => true,
            'group_code' => 'bagger',
            'buffer_after_hours' => 4,
        ]);
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $orgRole = Role::query()->where('name', $role)->where('team_id', $this->organization->id)->firstOrFail();
        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }

    private function rateCard(): RentalRateCard {
        $card = RentalRateCard::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'version' => 1,
            'status' => RentalRateCardStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
        $card->items()->createMany([
            ['organization_id' => $this->organization->id, 'kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'group_code' => 'bagger', 'amount' => '100.00', 'unit' => 'day'],
            ['organization_id' => $this->organization->id, 'kind' => 'cleaning', 'label' => 'Endreinigung', 'amount' => '50.00', 'unit' => 'flat'],
        ]);

        return $card;
    }

    /** @param array<string, mixed> $overrides */
    private function openCase(array $overrides = [], ?array $assetIds = null): RentalCase {
        return app(RentalCaseService::class)->open($this->organization, $this->admin, array_merge([
            'customer_id' => $this->customer->id,
            'starts_at' => now()->addDay()->setTime(8, 0),
            'ends_at' => now()->addDays(3)->setTime(17, 0),
        ], $overrides), $assetIds ?? [$this->asset->id]);
    }

    public function test_case_gets_number_and_freezes_rate_card_snapshot(): void {
        $card = $this->rateCard();
        $case = $this->openCase(['rental_rate_card_id' => $card->id]);

        $this->assertMatchesRegularExpression('/^VER-\d{4}-\d{4}$/', $case->number);
        $this->assertSame(RentalCaseStatus::Draft, $case->status);
        $this->assertSame(1, (int) data_get($case->terms_snapshot, 'version'));
        $this->assertCount(2, (array) data_get($case->terms_snapshot, 'items'));

        // D10: spätere Preisänderungen bewerten die Akte nicht um.
        $card->items()->update(['amount' => '999.00']);
        $this->assertSame('100.00', (string) data_get($case->fresh()->terms_snapshot, 'items.0.amount'));
    }

    public function test_reservation_blocks_availability_including_buffers(): void {
        $case = $this->openCase();
        app(RentalCaseService::class)->reserve($case, $this->admin);

        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);
        $this->assertDatabaseHas('rental_reservations', [
            'rental_case_id' => $case->id,
            'asset_id' => $this->asset->id,
            'buffer_after_hours' => 4,
        ]);

        // Direkt anschließender Zeitraum kollidiert wegen des 4h-Puffers.
        $availability = app(RentalAvailabilityService::class);
        $conflict = $availability->findConflict(
            $this->asset,
            $case->ends_at->copy()->addHour(),
            $case->ends_at->copy()->addHours(6),
        );
        $this->assertNotNull($conflict);

        // Nach dem Pufferfenster ist das Gerät wieder frei.
        $this->assertTrue($availability->isAvailable(
            $this->asset,
            $case->ends_at->copy()->addHours(5),
            $case->ends_at->copy()->addHours(10),
        ));
    }

    public function test_double_booking_is_rejected(): void {
        $first = $this->openCase();
        app(RentalCaseService::class)->reserve($first, $this->admin);

        $second = $this->openCase();
        $this->expectException(RentalConflictException::class);
        app(RentalCaseService::class)->reserve($second, $this->admin);
    }

    public function test_shared_asset_block_prevents_rental_and_exception_releases_it(): void {
        $blocks = app(AssetBlockService::class);
        $block = $blocks->block($this->asset, AssetBlockReason::Safety, $this->admin, 'Bremse defekt gemeldet');

        $case = $this->openCase();
        try {
            app(RentalCaseService::class)->reserve($case, $this->admin);
            $this->fail('Gesperrtes Asset darf nicht reserviert werden.');
        } catch (AssetNotUsableException $e) {
            $this->assertNotNull($e->block);
        }

        // D12: befristete, begründete Ausnahmefreigabe je Kontext.
        try {
            $blocks->grantException($block, $this->admin, 'rental', 'kurz', now()->addDay());
            $this->fail('Zu kurze Begründung darf nicht akzeptiert werden.');
        } catch (\InvalidArgumentException) {
            // erwartet (< 20 Zeichen)
        }

        $blocks->grantException($block, $this->admin, 'rental', 'Freigabe nach Rücksprache mit Sicherheitsfachkraft.', now()->addDays(7));
        app(RentalCaseService::class)->reserve($case->fresh(), $this->admin);
        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);

        // Die Ausnahme gilt NUR für den Verleih-Kontext.
        $this->assertFalse(app(AssetUsageGuard::class)->isUsable($this->asset, 'dispatch'));
    }

    public function test_overdue_inspection_blocks_rentable_asset_with_inspection_gate(): void {
        RentalProfile::query()->where('asset_id', $this->asset->id)->update(['requires_inspection' => true]);
        $this->asset->forceFill(['next_inspection_on' => now()->subDay()->toDateString()])->save();

        $case = $this->openCase();
        $this->expectException(AssetNotUsableException::class);
        app(RentalCaseService::class)->reserve($case, $this->admin);
    }

    public function test_handover_and_return_with_block_follow_up(): void {
        $case = $this->openCase();
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);

        $report = $service->handover($case->fresh(), $this->asset, $this->admin, [
            'condition' => 'good',
            'meter_value' => '120.5',
            'signature_name' => 'Max Kunde',
            'accessory_items' => [['label' => 'Ladegerät', 'quantity' => 1]],
        ]);
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);
        $this->assertDatabaseHas('rental_accessory_items', [
            'report_id' => $report->id,
            'label' => 'Ladegerät',
        ]);

        $service->returnAsset($case->fresh(), $this->asset, $this->admin, [
            'condition' => 'damaged',
            'damages' => 'Hydraulikschlauch beschädigt',
            'follow_up' => RentalReturnFollowUp::Repair->value,
            'follow_up_note' => 'Schlauch muss vor dem nächsten Einsatz getauscht werden.',
        ]);

        $fresh = $case->fresh();
        $this->assertSame(RentalCaseStatus::Returned, $fresh->status);
        $this->assertNotNull($fresh->actual_return_at);

        // Folgeentscheidung Reparatur → Sperre im gemeinsamen Modell (D12)
        // mit Quelle = Rücknahmeprotokoll.
        $this->assertDatabaseHas('asset_blocks', [
            'asset_id' => $this->asset->id,
            'reason' => AssetBlockReason::RentalDamage->value,
        ]);
        $this->assertFalse(app(AssetUsageGuard::class)->isUsable($this->asset, 'rental'));
    }

    public function test_return_with_claim_follow_up_opens_linked_claim_case(): void {
        $case = $this->openCase();
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);
        $service->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);

        $service->returnAsset($case->fresh(), $this->asset, $this->admin, [
            'condition' => 'damaged',
            'damages' => 'Kratzer und gebrochene Abdeckung',
            'follow_up' => RentalReturnFollowUp::Claim->value,
            'follow_up_note' => 'Kunde bestreitet den Schaden — Streitfall.',
        ]);

        $claim = \App\Models\Claims\ClaimCase::query()->where('asset_id', $this->asset->id)->first();
        $this->assertNotNull($claim);
        $this->assertDatabaseHas('claim_case_links', [
            'claim_case_id' => $claim->id,
            'linkable_type' => $case->getMorphClass(),
            'linkable_id' => $case->id,
        ]);
    }

    public function test_extension_checks_follow_up_bookings_and_swap_hands_over_remaining_period(): void {
        $case = $this->openCase();
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);

        // Folgebelegung direkt nach dem Puffer.
        RentalReservation::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'kind' => 'hard',
            'status' => 'active',
            'starts_at' => $case->ends_at->copy()->addHours(5),
            'ends_at' => $case->ends_at->copy()->addDays(2),
        ]);

        try {
            $service->extend($case->fresh(), $this->admin, $case->ends_at->copy()->addDays(2));
            $this->fail('Verlängerung in eine Folgebelegung darf nicht möglich sein.');
        } catch (RentalConflictException) {
            // erwartet
        }

        // Tauschgerät: Ersatz übernimmt den Restzeitraum.
        $replacement = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger 2']);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $replacement->id,
            'is_rentable' => true,
        ]);

        $current = $case->caseAssets()->firstOrFail();
        $service->swapAsset($case->fresh(), $current, $replacement, $this->admin, 'Hydraulikproblem');

        $this->assertSame('swapped', (string) $current->fresh()->status);
        $this->assertDatabaseHas('rental_case_assets', [
            'rental_case_id' => $case->id,
            'asset_id' => $replacement->id,
        ]);
        $this->assertSame(
            0,
            RentalReservation::query()->where('asset_id', $this->asset->id)->active()
                ->get()->filter(fn ($r) => $r->rental_case_id === $case->id)->count(),
        );
    }

    public function test_overdue_scanner_flags_case_and_notifies_once(): void {
        \Illuminate\Support\Facades\Notification::fake();
        // Betroffene Person = Akten-Verantwortlicher (notify_affected).
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);
        $case = $this->openCase([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'responsible_user_id' => $responsible->id,
        ]);
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);
        $service->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);

        $service->escalateOverdue($this->organization);
        $this->assertSame(RentalCaseStatus::Overdue, $case->fresh()->status);
        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => \App\Enums\Notification\NotificationEvent::RentalReturnOverdue->value,
            'subject_id' => $case->id,
        ]);

        // Idempotent: zweiter Lauf erzeugt keinen weiteren Initial-Eintrag.
        $service->escalateOverdue($this->organization);
        $this->assertSame(1, \App\Models\Notification\NotificationDispatchLog::query()
            ->where('event', \App\Enums\Notification\NotificationEvent::RentalReturnOverdue->value)
            ->where('subject_id', $case->id)
            ->where('stage', \App\Models\Notification\NotificationDispatchLog::STAGE_INITIAL)
            ->count());

        // Verlängerung holt die Akte aus der Überfälligkeit.
        $service->extend($case->fresh(), $this->admin, now()->addDay());
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);
    }

    public function test_charge_suggestions_come_from_snapshot_and_damage_requires_reason(): void {
        $card = $this->rateCard();
        $case = $this->openCase(['rental_rate_card_id' => $card->id]);
        $billing = app(RentalBillingService::class);

        $suggestions = $billing->suggestCharges($case);
        $kinds = array_column($suggestions, 'kind');
        $this->assertContains('daily_rate', $kinds);
        $this->assertContains('cleaning', $kinds);
        $daily = collect($suggestions)->firstWhere('kind', 'daily_rate');
        $this->assertGreaterThanOrEqual(2, $daily['quantity']);

        $this->expectException(\InvalidArgumentException::class);
        $billing->addCharge($case, $this->admin, [
            'kind' => RentalChargeKind::Damage->value,
            'label' => 'Kratzer',
            'quantity' => 1,
            'unit' => 'flat',
            'unit_price' => 120,
        ]);
    }

    public function test_local_invoicing_marks_charges_and_links_items(): void {
        $case = $this->openCase();
        $billing = app(RentalBillingService::class);

        $charge = $billing->addCharge($case, $this->admin, [
            'kind' => RentalChargeKind::DailyRate->value,
            'label' => 'Tagessatz',
            'quantity' => 3,
            'unit' => 'day',
            'unit_price' => 100,
        ]);
        $this->assertSame('300.00', (string) $charge->amount);

        // Ohne Freigabe keine Übergabe.
        try {
            $billing->invoiceReleasedCharges($case, $this->admin);
            $this->fail('Ohne freigegebene Positionen darf keine Rechnung entstehen.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $billing->releaseCharge($charge, $this->admin);
        $invoice = $billing->invoiceReleasedCharges($case, $this->admin);

        $this->assertNotNull($invoice);
        $this->assertSame(RentalChargeStatus::Invoiced, $charge->fresh()->status);
        $this->assertSame($invoice->id, (int) $charge->fresh()->invoice_id);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'rental_charge_id' => $charge->id,
        ]);

        // Löschen der Position gibt den Quellposten wieder frei (Hook).
        $invoice->items()->first()->delete();
        $this->assertSame(RentalChargeStatus::Released, $charge->fresh()->status);
    }

    public function test_external_billing_sovereignty_transfers_without_local_invoice(): void {
        $this->customer->forceFill(['billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice->value])->save();
        $case = $this->openCase();
        $billing = app(RentalBillingService::class);

        $charge = $billing->addCharge($case, $this->admin, [
            'kind' => RentalChargeKind::FlatRate->value,
            'label' => 'Wochenendpauschale',
            'quantity' => 1,
            'unit' => 'flat',
            'unit_price' => 250,
        ]);
        $billing->releaseCharge($charge, $this->admin);

        $invoice = $billing->invoiceReleasedCharges($case, $this->admin);
        $this->assertNull($invoice);
        $this->assertSame(RentalChargeStatus::Transferred, $charge->fresh()->status);
        $this->assertSame(0, \App\Models\Invoice::query()->count());

        $billing->recordExternalReference($charge->fresh(), 'RE-2026-0815');
        $this->assertSame('RE-2026-0815', $charge->fresh()->external_reference);
    }

    public function test_deposit_is_own_financial_transaction_with_mandatory_retention_reason(): void {
        $case = $this->openCase(['deposit_amount' => '150.00']);
        $billing = app(RentalBillingService::class);

        $deposit = $billing->requestDeposit($case, $this->admin, 150.0);
        $billing->markDepositReceived($deposit, $this->admin);

        try {
            $billing->settleDeposit($deposit->fresh(), $this->admin, 50.0, null);
            $this->fail('Einbehalt ohne Begründung darf nicht möglich sein.');
        } catch (\InvalidArgumentException) {
            // erwartet
        }

        $billing->settleDeposit($deposit->fresh(), $this->admin, 50.0, 'Reinigungskosten wegen starker Verschmutzung.');
        $this->assertSame(RentalDepositStatus::PartiallyRetained, $deposit->fresh()->status);

        // D10: Kaution erzeugt KEINE Mietposition.
        $this->assertSame(0, $case->charges()->count());
    }

    public function test_role_separation_finance_vs_handover(): void {
        $case = $this->openCase();
        app(RentalCaseService::class)->reserve($case, $this->admin);

        // Teamleitung: darf übergeben, aber keine Positionen freigeben.
        $lead = $this->userWithRole(\App\Enums\User\UserRole::Teamleitung->value);
        $this->actingAs($lead)->post(route('rental.handover', $case), [
            'asset_id' => $this->asset->sqid,
            'condition' => 'good',
        ])->assertRedirect();

        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, [
            'kind' => RentalChargeKind::DailyRate->value,
            'label' => 'Tagessatz',
            'quantity' => 1,
            'unit' => 'day',
            'unit_price' => 100,
        ]);
        $this->actingAs($lead)->post(route('rental.charges.release', $charge))->assertForbidden();

        // Buchhaltung: gibt frei, protokolliert aber keine Übergaben.
        $accounting = $this->userWithRole(\App\Enums\User\UserRole::Buchhaltung->value);
        $this->actingAs($accounting)->post(route('rental.charges.release', $charge))->assertRedirect();
        $this->assertSame(RentalChargeStatus::Released, $charge->fresh()->status);
        $this->actingAs($accounting)->post(route('rental.return', $case), [
            'asset_id' => $this->asset->sqid,
            'condition' => 'good',
            'follow_up' => 'none',
        ])->assertForbidden();
    }

    public function test_portal_shows_only_own_rentals_and_confirms_handover(): void {
        $case = $this->openCase();
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);
        $report = $service->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignAsset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $foreignAsset->id,
            'is_rentable' => true,
        ]);
        $foreignCase = $this->openCase(['customer_id' => $otherCustomer->id], [$foreignAsset->id]);

        $portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);

        $this->actingAs($portalUser, 'customer')->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertSee($case->number)
            ->assertDontSee($foreignCase->number);

        $this->actingAs($portalUser, 'customer')->get(route('customer.rentals.show', $foreignCase))->assertNotFound();

        $this->actingAs($portalUser, 'customer')
            ->post(route('customer.rentals.confirm', [$case, $report]))
            ->assertRedirect();
        $this->assertNotNull($report->fresh()->portal_confirmed_at);
    }

    public function test_rate_card_versions_are_immutable_after_activation(): void {
        $this->actingAs($this->admin)->post(route('rental.rates.store'), ['name' => 'Standard'])->assertRedirect();
        $card = RentalRateCard::query()->where('name', 'Standard')->firstOrFail();

        $this->actingAs($this->admin)->post(route('rental.rates.items.store', $card), [
            'kind' => 'daily_rate',
            'label' => 'Tagessatz',
            'amount' => 80,
            'unit' => 'day',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('rental.rates.activate', $card))->assertRedirect();
        $this->assertSame(RentalRateCardStatus::Active, $card->fresh()->status);

        // Aktive Version ist unveränderlich.
        $this->actingAs($this->admin)->post(route('rental.rates.items.store', $card), [
            'kind' => 'cleaning', 'label' => 'Reinigung', 'amount' => 30, 'unit' => 'flat',
        ])->assertSessionHasErrors();

        // Neue Version übernimmt Positionen und löst die aktive beim Aktivieren ab.
        $this->actingAs($this->admin)->post(route('rental.rates.store'), ['name' => 'Standard'])->assertRedirect();
        $v2 = RentalRateCard::query()->where('name', 'Standard')->where('version', 2)->firstOrFail();
        $this->assertSame(1, $v2->items()->count());
        $this->actingAs($this->admin)->post(route('rental.rates.activate', $v2))->assertRedirect();
        $this->assertSame(RentalRateCardStatus::Retired, $card->fresh()->status);
    }

    public function test_case_can_be_created_via_http_with_sqids(): void {
        $this->actingAs($this->admin)->post(route('rental.store'), [
            'customer_id' => $this->customer->sqid,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'asset_ids' => [$this->asset->sqid],
        ])->assertRedirect();

        $this->assertDatabaseHas('rental_cases', [
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertDatabaseHas('rental_case_assets', ['asset_id' => $this->asset->id]);
    }

    public function test_module_gating_blocks_without_license(): void {
        $freeOrg = \App\Models\Organization::factory()->free()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($freeOrg->id);
        $freeAdmin = User::factory()->admin()->create(['organization_id' => $freeOrg->id]);

        $this->actingAs($freeAdmin)->get(route('rental.index'))->assertStatus(423);
    }
}
