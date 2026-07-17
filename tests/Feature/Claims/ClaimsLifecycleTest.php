<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimsLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Claims;

use App\Enums\Claims\{ClaimFinancialKind, ClaimKind, ClaimRmaDisposition, ClaimStatus, ClaimVerdict};
use App\Enums\Inventory\{SerialSource, SerialStatus, StockState};
use App\Models\{Article, ArticleVariant, Customer, Invoice, StockSerial, User, Warehouse};
use App\Models\Claims\ClaimCase;
use App\Services\Claims\{ClaimCaseService, ClaimFinancialService, ClaimRmaService};
use App\Services\Inventory\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 072 (MVP-246–257): Fallakte mit Nummernkreis + Dubletten,
 * Bewertung mit P2-Snapshot (Seriennummernfakten), Entscheidung mit
 * Pflichtbegründung, RMA-Quarantäne mit idempotenten Lagerbuchungen,
 * kaufmännische Folge per Vier-Augen (D1: reason_kind statt Belegtyp),
 * Regressfristen, Portal-Sicht (nur eigene Fälle) und Rechte-Trennung.
 */
final class ClaimsLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function openCase(array $overrides = []): ClaimCase {
        return app(ClaimCaseService::class)->open($this->organization, $this->admin, array_merge([
            'title' => 'Heizungsventil undicht',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    public function test_case_gets_number_and_duplicates_are_detected(): void {
        $first = $this->openCase(['serial_no' => 'SN-1000']);
        $this->assertMatchesRegularExpression('/^REK-\d{4}-\d{4}$/', $first->number);
        $this->assertSame(ClaimStatus::Received, $first->status);
        $this->assertNotNull($first->due_at);

        $duplicates = app(ClaimCaseService::class)->duplicates([
            'customer_id' => $this->customer->id,
            'serial_no' => 'SN-1000',
        ]);
        $this->assertTrue($duplicates->contains('id', $first->id));
    }

    public function test_assessment_freezes_serial_snapshot_and_decision_requires_it(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id]);
        $serial = StockSerial::query()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'serial_no' => 'SN-77',
            'status' => SerialStatus::Shipped->value,
            'source' => SerialSource::Purchased->value,
            'customer_id' => $this->customer->id,
        ]);

        $case = $this->openCase(['serial_no' => 'SN-77', 'article_id' => $article->id, 'stock_serial_id' => $serial->id]);
        $service = app(ClaimCaseService::class);

        // Entscheidung ohne Bewertung → Fehler.
        try {
            $service->decide($case, $this->admin, 'accepted', 'Begründung mit genug Zeichen.');
            $this->fail('Entscheidung ohne Bewertung darf nicht möglich sein.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $assessment = $service->assess($case, $this->admin, ClaimKind::WarrantyLegal, ClaimVerdict::Justified, 'Ventil innerhalb der Gewährleistung defekt.');
        $this->assertTrue((bool) $assessment->snapshot['serial_shipped_to_customer']);
        $this->assertSame(ClaimStatus::Assessing, $case->fresh()->status);

        $decision = $service->decide($case->fresh(), $this->admin, 'accepted', 'Nacherfüllung wird gewährt (§ 439 BGB).');
        $this->assertSame('warranty_legal', $decision->snapshot['claim_kind']);
        $this->assertSame(ClaimStatus::Decided, $case->fresh()->status);
    }

    public function test_rejected_decision_closes_case_terminally(): void {
        $case = $this->openCase();
        $service = app(ClaimCaseService::class);
        $service->assess($case, $this->admin, ClaimKind::Unfounded, ClaimVerdict::Rejected, 'Fehlbedienung laut Prüfbericht dokumentiert.');
        $service->decide($case->fresh(), $this->admin, 'rejected', 'Kein Mangel — Ablehnung mit Begründung.');

        $this->assertSame(ClaimStatus::Rejected, $case->fresh()->status);
        // Terminal: kein weiterer Übergang erlaubt.
        try {
            $service->transition($case->fresh(), ClaimStatus::Closed);
            $this->fail('Terminale Fälle dürfen keinen Statuswechsel erlauben.');
        } catch (\RuntimeException) {
            // erwartet
        }
    }

    public function test_rma_receive_books_quarantine_idempotently_and_disposition_scraps(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $serial = StockSerial::query()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'serial_no' => 'SN-RMA-1',
            'status' => SerialStatus::Shipped->value,
            'source' => SerialSource::Purchased->value,
            'customer_id' => $this->customer->id,
        ]);

        $case = $this->openCase(['serial_no' => 'SN-RMA-1']);
        $rmaService = app(ClaimRmaService::class);
        $rma = $rmaService->announce($case, [
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'stock_serial_id' => $serial->id,
            'serial_no' => 'SN-RMA-1',
            'qty' => '1',
            'warehouse_id' => $warehouse->id,
        ]);
        $this->assertMatchesRegularExpression('/^RMA-\d{4}-\d{4}$/', $rma->rma_number);

        $rmaService->receive($rma, $this->admin, ['stock_state' => 'quality']);
        // Doppelte Buchung wird über den Idempotenzschlüssel verhindert.
        $rmaService->receive($rma->fresh(), $this->admin, ['stock_state' => 'quality']);

        $ledger = app(InventoryLedger::class);
        $this->assertSame('1.0000', $ledger->balance($variant, $warehouse, StockState::Quality));
        $this->assertSame(SerialStatus::Returned, $serial->fresh()->status);

        $inspection = $rmaService->inspect($rma->fresh(), $this->admin, ['result' => 'defect_confirmed', 'findings' => 'Ventilsitz gebrochen.']);
        $this->assertTrue($inspection->serial_checked);
        $this->assertSame('shipped_to_customer', $inspection->serial_check_result);

        $rmaService->decideDisposition($rma->fresh(), $this->admin, ClaimRmaDisposition::Scrap, 'irreparabel');
        $this->assertSame('0.0000', $ledger->balance($variant, $warehouse, StockState::Quality));
        $this->assertSame(SerialStatus::Scrapped, $serial->fresh()->status);
    }

    public function test_financial_outcome_requires_four_eyes_and_sets_reason_kind(): void {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE-2026-0042',
            'status' => Invoice::STATUS_PAID,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-06-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);
        $case = $this->openCase(['invoice_id' => $invoice->id]);

        $service = app(ClaimFinancialService::class);
        $outcome = $service->propose($case, $this->admin, ClaimFinancialKind::PriceReduction, [
            'amount' => '50.00',
            'invoice_id' => $invoice->id,
            'justification' => 'Minderung wegen bestätigtem Mangel.',
        ]);

        // Selbstfreigabe gesperrt (Vier-Augen-Prinzip).
        try {
            $service->approve($outcome, $this->admin);
            $this->fail('Selbstfreigabe muss gesperrt sein.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $approver = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $service->approve($outcome, $approver);
        $executed = $service->execute($outcome->fresh(), $approver);

        $result = $executed->resultInvoice;
        $this->assertNotNull($result);
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $result->type);
        // D1: strukturierter Grund am Beleg statt neuem Belegtyp.
        $this->assertSame('price_reduction', $result->reason_kind);
    }

    public function test_external_billing_sovereignty_blocks_local_correction_document(): void {
        // Kunde wird extern fakturiert (Lexoffice führend) — Lexoffice kennt
        // keinen Minderungsbeleg; die Korrektur entsteht DORT als
        // Rechnungskorrektur, workDiary erzeugt keinen lokalen Beleg.
        $externalCustomer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice->value,
        ]);
        $case = $this->openCase(['customer_id' => $externalCustomer->id]);

        $service = app(ClaimFinancialService::class);
        $outcome = $service->propose($case, $this->admin, ClaimFinancialKind::PriceReduction, [
            'amount' => '25.00',
            'justification' => 'Minderung — Beleg entsteht im führenden System.',
        ]);
        $approver = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $service->approve($outcome, $approver);
        $executed = $service->execute($outcome->fresh(), $approver);

        $this->assertNull($executed->result_invoice_id);
        $this->assertSame('executed', $executed->status->value);
        $this->assertStringContainsString('Beleghoheit', (string) $executed->note);
        $this->assertSame(0, Invoice::query()->where('customer_id', $externalCustomer->id)->count());

        // Belegnummer aus Lexoffice nachtragen (nur für externe Folgen erlaubt).
        $service->recordExternalReference($executed, 'RK-LX-2026-0815');
        $this->assertSame('RK-LX-2026-0815', $executed->fresh()->external_reference);
    }

    public function test_recourse_submission_sets_response_deadline(): void {
        $supplier = \App\Models\Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $case = $this->openCase(['supplier_id' => $supplier->id]);
        $teamlead = $this->userWithRole('teamleitung');

        $this->actingAs($teamlead)->post(route('claims.recourses.store', $case), [
            'supplier_id' => $supplier->sqid,
            'amount_claimed' => '120.00',
        ])->assertRedirect();

        $recourse = $case->supplierRecourses()->firstOrFail();
        $this->actingAs($teamlead)->put(route('claims.recourses.update', $recourse), [
            'status' => 'submitted',
        ])->assertRedirect();

        $fresh = $recourse->fresh();
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNotNull($fresh->response_due_at);
        $this->assertTrue($fresh->response_due_at->greaterThan(now()->addDays(13)));
    }

    public function test_permissions_separate_roles(): void {
        $case = $this->openCase();
        $buchhaltung = $this->userWithRole('buchhaltung');
        $support = $this->userWithRole('support');

        // Buchhaltung: lesen + Finanzfreigabe, aber keine Entscheidung.
        $this->actingAs($buchhaltung)->get(route('claims.show', $case))->assertOk();
        $this->actingAs($buchhaltung)->post(route('claims.assess', $case), [
            'claim_kind' => 'goodwill', 'verdict' => 'justified', 'justification' => 'Nicht meine Rolle, sollte 403 sein.',
        ])->assertForbidden();

        // Support: Annahme/Pflege, aber kein Lagerprozess.
        $this->actingAs($support)->post(route('claims.rma.store', $case), [])->assertForbidden();
    }

    /** B1/MVP-007: Ursachencode-Selects der Fallakte senden Sqids (Konvention: Sqid in Formularen). */
    public function test_show_renders_classification_options_as_sqids(): void {
        $classification = \App\Models\Classification::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => \App\Enums\Classification\ClassificationDomain::DefectType->value,
            'code' => 'leak',
            'label' => 'Undichtigkeit',
            'active' => true,
        ]);
        $case = $this->openCase();

        $this->actingAs($this->admin)
            ->get(route('claims.show', $case))
            ->assertOk()
            ->assertSee('value="' . $classification->sqid . '"', false);
    }

    public function test_portal_shows_only_own_claims(): void {
        $case = $this->openCase();
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignCase = $this->openCase(['customer_id' => $otherCustomer->id, 'title' => 'Fremder Fall']);

        $portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);

        $this->actingAs($portalUser, 'customer')->get(route('customer.claims.index'))
            ->assertOk()
            ->assertSee($case->number)
            ->assertDontSee($foreignCase->number);

        $this->actingAs($portalUser, 'customer')->get(route('customer.claims.show', $foreignCase))->assertNotFound();

        $this->actingAs($portalUser, 'customer')->post(route('customer.claims.note', $case), [
            'note' => 'Foto folgt per Mail — Gerät tropft weiterhin.',
        ])->assertRedirect();
        $this->assertDatabaseHas('claim_evidence', [
            'claim_case_id' => $case->id,
            'kind' => 'message',
        ]);
    }

    public function test_escalation_notifies_once_per_day(): void {
        \Illuminate\Support\Facades\Notification::fake();
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->openCase(['due_at' => now()->subDay(), 'responsible_user_id' => $responsible->id]);

        $service = app(ClaimCaseService::class);
        $this->assertSame(1, $service->escalateOverdue($this->organization));
        // gleicher Tag → keine zweite Eskalation
        $this->assertSame(0, $service->escalateOverdue($this->organization));

        \Illuminate\Support\Facades\Notification::assertSentToTimes($responsible, \App\Notifications\GenericEventNotification::class, 1);
    }

    public function test_module_gating_blocks_without_license(): void {
        $freeOrg = \App\Models\Organization::factory()->free()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($freeOrg->id);
        $freeAdmin = User::factory()->admin()->create(['organization_id' => $freeOrg->id]);

        $this->actingAs($freeAdmin)->get(route('claims.index'))->assertStatus(423);
    }
}
