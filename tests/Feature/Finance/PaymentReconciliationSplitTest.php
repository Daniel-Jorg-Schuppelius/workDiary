<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReconciliationSplitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AllocationKind, BalanceCheck, MatchStatus, TransactionDirection};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Finance\{BankImportException, BankImportService, FinancialFormatsSupport, MatchingService, ReconciliationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Automatische Sammelbuchungs-Auflösung (Feature 045, Toolkit-Folgepaket 2 zu
 * MVP-334): CAMT-Buchungen mit mehreren TxDtls führen ihre Einzeltransaktionen
 * als Detail-Liste mit (Bank-Buchung bleibt EINE Zeile — Kontoauszugs-Treue);
 * der MatchingService erzeugt je Detail einen Zuordnungsvorschlag, die
 * Split-UI ist vorbefüllt und die Bestätigung läuft über den EXISTIERENDEN
 * confirm-Mehrfach-Pfad. Sammel-Rücklastschriften speisen suggestReturnOrigins
 * je Detail. Einzel-TxDtls-Importe bleiben unverändert (Charakterisierung).
 */
class PaymentReconciliationSplitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }

    private function fixtureFile(string $name): UploadedFile {
        return new UploadedFile(base_path('tests/Fixtures/finance/' . $name), $name, 'text/xml', null, true);
    }

    private function importService(): BankImportService {
        return app(BankImportService::class);
    }

    private function makeInvoice(string $number, string $total = '119.00'): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => $total,
            'created_by' => $this->admin->id,
        ]);
    }

    private function importBatchTransaction(string $fixture = 'camt053_batch_sample.xml'): BankTransaction {
        $statements = $this->importService()->import($this->fixtureFile($fixture), $this->organization->id);

        return $statements[0]->transactions()->firstOrFail();
    }

    public function test_batch_import_persists_details_only_for_multiple_txdtls(): void {
        // Sammelbuchung: EIN Ntry mit drei TxDtls ⇒ EINE Bank-Zeile
        // (Kontoauszugs-Treue) mit Detail-Liste.
        $tx = $this->importBatchTransaction();

        $this->assertSame('269.00', $tx->amount);
        $this->assertSame(TransactionDirection::Credit, $tx->direction);
        $this->assertSame(1, BankTransaction::query()->where('bank_statement_id', $tx->bank_statement_id)->count());
        $this->assertSame(BalanceCheck::Ok, $tx->statement->balance_check);

        $details = $tx->transactionDetails();
        $this->assertCount(3, $details);
        $this->assertTrue($tx->hasSplitDetails());
        $this->assertSame('119.00', $details[0]['amount']);
        $this->assertSame('RE-2026-0201', $details[0]['end_to_end_id']);
        $this->assertSame('Muster GmbH', $details[0]['counterparty_name']);
        $this->assertNotNull($details[0]['counterparty_iban_hash']);
        $this->assertSame('100.00', $details[1]['amount']);
        $this->assertSame('50.00', $details[2]['amount']);

        // Summe der Details == Buchungsbetrag.
        $sum = array_sum(array_map(static fn(array $d): float => (float) $d['amount'], $details));
        $this->assertEqualsWithDelta((float) $tx->amount, $sum, 0.001);

        // PII der Details liegt verschlüsselt at-rest (kein Klartext in der DB).
        $raw = DB::table('bank_transactions')->where('id', $tx->id)->value('transaction_details');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('Muster GmbH', (string) $raw);
        $this->assertStringNotContainsString('DE02120300000000202051', (string) $raw);

        // Einzel-TxDtls-Buchung: KEINE Detail-Liste, kein Split-Vorschlag.
        $single = $this->importService()->import($this->fixtureFile('camt053_sample.xml'), $this->organization->id)[0]
            ->transactions()->firstOrFail();
        $this->assertNull(DB::table('bank_transactions')->where('id', $single->id)->value('transaction_details'));
        $this->assertFalse($single->hasSplitDetails());
        $this->assertSame([], app(MatchingService::class)->suggestSplitFor($single));
    }

    /**
     * Charakterisierung (BC): der Import einer Einzel-TxDtls-Datei persistiert
     * exakt dieselben Felder wie vor dem Toolkit-Folgepaket 2 — die
     * Einzelwert-Accessors des Toolkits liefern unverändert das erste TxDtls.
     */
    public function test_single_txdtls_import_is_unchanged_characterization(): void {
        $statements = $this->importService()->import($this->fixtureFile('camt053_sample.xml'), $this->organization->id);

        $statement = $statements[0];
        $this->assertSame(1, $statement->tx_count);
        $this->assertSame(BalanceCheck::Ok, $statement->balance_check);

        $tx = $statement->transactions()->firstOrFail();
        $this->assertSame('2026-05-15', $tx->booking_date->toDateString());
        $this->assertSame('119.00', $tx->amount);
        $this->assertSame(TransactionDirection::Credit, $tx->direction);
        $this->assertSame('EUR', $tx->currency->value);
        $this->assertSame('RE-2026-0007', $tx->end_to_end_id);
        $this->assertNull($tx->mandate_ref);
        $this->assertSame('Muster GmbH', $tx->counterparty_name);
        $this->assertSame('DE02120300000000202051', $tx->counterparty_iban);
        $this->assertNotNull($tx->counterparty_iban_hash);
        // Bestandsverhalten (auch schon unter Toolkit v1.5.5): getPurpose()
        // liefert nur Purp/Prtry — der Ustrd-Verwendungszweck landet auf
        // Entry-Ebene NICHT in purpose (die Referenzen kommen aus der
        // EndToEndId). Byte-identisch heißt: das bleibt so.
        $this->assertNull($tx->purpose);
        $this->assertContains('RE-2026-0007', $tx->extracted_refs);
        $this->assertContains('RE20260007', $tx->extracted_refs);
        $this->assertFalse($tx->is_reversal);
        $this->assertNull($tx->return_reason);
        $this->assertSame(MatchStatus::Unmatched, $tx->match_status);
        $this->assertNull($tx->transaction_details);
    }

    public function test_batch_reimport_is_rejected_as_duplicate(): void {
        $this->importBatchTransaction();
        $this->assertSame(1, BankTransaction::query()->count());

        try {
            $this->importService()->import($this->fixtureFile('camt053_batch_sample.xml'), $this->organization->id);
            $this->fail('Re-Import derselben Sammelbuchungs-Datei muss als Dublette abgelehnt werden.');
        } catch (BankImportException) {
            // erwartet: Datei-Hash-Dublette.
        }

        $this->assertSame(1, BankTransaction::query()->count());
    }

    public function test_split_suggestions_match_details_by_reference_and_amount(): void {
        $first = $this->makeInvoice('RE-2026-0201');
        $second = $this->makeInvoice('RE-2026-0202', '100.00');
        $third = $this->makeInvoice('RE-2026-0203', '50.00');

        $tx = $this->importBatchTransaction();
        $rows = app(MatchingService::class)->suggestSplitFor($tx);

        $this->assertCount(3, $rows);

        $this->assertNotNull($rows[0]['suggestion']);
        $this->assertTrue($rows[0]['suggestion']['target']->is($first));
        $this->assertContains('reference', $rows[0]['suggestion']['reasons']);
        $this->assertContains('amount', $rows[0]['suggestion']['reasons']);
        $this->assertSame('119.00', $rows[0]['detail']['amount']);

        $this->assertNotNull($rows[1]['suggestion']);
        $this->assertTrue($rows[1]['suggestion']['target']->is($second));
        $this->assertSame('100.00', $rows[1]['detail']['amount']);

        $this->assertNotNull($rows[2]['suggestion']);
        $this->assertTrue($rows[2]['suggestion']['target']->is($third));
        $this->assertSame('50.00', $rows[2]['detail']['amount']);
    }

    public function test_split_suggestions_respect_org_isolation(): void {
        // Die einzig passenden Rechnungen gehören einer FREMDEN Organisation —
        // je Detail darf KEIN Vorschlag entstehen (OrganizationScope).
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremd AG',
            'currency' => 'EUR',
        ]);
        foreach ([['RE-2026-0201', '119.00'], ['RE-2026-0202', '100.00'], ['RE-2026-0203', '50.00']] as [$number, $total]) {
            Invoice::create([
                'organization_id' => $otherOrg->id,
                'customer_id' => $otherCustomer->id,
                'number' => $number,
                'status' => Invoice::STATUS_ISSUED,
                'type' => Invoice::TYPE_INVOICE,
                'category' => Invoice::CATEGORY_SERVICE,
                'currency' => 'EUR',
                'tax_rate' => '19.00',
                'subtotal' => '100.00',
                'tax_amount' => '19.00',
                'total' => $total,
            ]);
        }

        $tx = $this->importBatchTransaction();
        $rows = app(MatchingService::class)->suggestSplitFor($tx);

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertNull($row['suggestion']);
        }
    }

    public function test_show_renders_prefilled_split_rows(): void {
        $this->makeInvoice('RE-2026-0201');
        $this->makeInvoice('RE-2026-0202', '100.00');
        $this->makeInvoice('RE-2026-0203', '50.00');
        $tx = $this->importBatchTransaction();

        $response = $this->get(route('finance.reconciliation.show', $tx->statement->sqid));

        $response->assertOk();
        // Vorbefüllte Aufteilung statt leerer Mehrfachauswahl.
        $response->assertSee(__('bank.split.title'));
        $response->assertSee('RE-2026-0201');
        $response->assertSee('value="119.00"', false);
        $response->assertSee('value="100.00"', false);
        $response->assertSee('value="50.00"', false);
        $response->assertSee(__('bank.split.target_placeholder'));
    }

    public function test_confirm_split_creates_exact_partial_allocations(): void {
        $first = $this->makeInvoice('RE-2026-0201');
        $second = $this->makeInvoice('RE-2026-0202', '100.00');
        $third = $this->makeInvoice('RE-2026-0203', '50.00');
        $tx = $this->importBatchTransaction();

        // Bestätigung über den EXISTIERENDEN confirm-Mehrfach-Pfad (keine
        // neue Buchungsmechanik).
        $this->post(route('finance.reconciliation.confirm', $tx->sqid), [
            'allocations' => [
                ['type' => 'invoice', 'id' => $first->sqid, 'amount' => '119.00'],
                ['type' => 'invoice', 'id' => $second->sqid, 'amount' => '100.00'],
                ['type' => 'invoice', 'id' => $third->sqid, 'amount' => '50.00'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $tx->refresh();
        $this->assertSame(MatchStatus::Matched, $tx->match_status);
        $this->assertSame(3, $tx->allocations()->count());

        // Summe der Teil-Zuordnungen == Buchungsbetrag.
        $this->assertEqualsWithDelta((float) $tx->amount, (float) $tx->allocations()->sum('amount'), 0.001);

        $this->assertSame(Invoice::STATUS_PAID, $first->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $second->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $third->refresh()->status);
        $this->assertDatabaseHas('payment_reconciliation_events', [
            'bank_transaction_id' => $tx->id,
            'event' => 'confirmed',
        ]);
    }

    public function test_batch_return_suggests_origins_per_detail_and_processes_each(): void {
        $first = $this->makeInvoice('RE-2026-0201');
        $second = $this->makeInvoice('RE-2026-0202', '100.00');

        // Ursprüngliche Einzel-Zahlungen (mit EndToEndId + Mandat) bestätigen.
        $statement = BankStatement::factory()->create(['organization_id' => $this->organization->id]);
        $paymentOne = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => $statement->id,
            'amount' => '119.00',
            'direction' => TransactionDirection::Credit,
            'booking_date' => '2026-05-15',
            'end_to_end_id' => 'RE-2026-0201',
            'mandate_ref' => 'MNDT-0201',
            'match_status' => MatchStatus::Unmatched,
        ]);
        $paymentTwo = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => $statement->id,
            'amount' => '100.00',
            'direction' => TransactionDirection::Credit,
            'booking_date' => '2026-05-15',
            'end_to_end_id' => 'RE-2026-0202',
            'mandate_ref' => 'MNDT-0202',
            'match_status' => MatchStatus::Unmatched,
        ]);
        app(ReconciliationService::class)->confirm($paymentOne, [['type' => Invoice::class, 'id' => $first->id, 'amount' => 119.00]]);
        app(ReconciliationService::class)->confirm($paymentTwo, [['type' => Invoice::class, 'id' => $second->id, 'amount' => 100.00]]);
        $originalOne = PaymentAllocation::query()->where('bank_transaction_id', $paymentOne->id)->firstOrFail();
        $originalTwo = PaymentAllocation::query()->where('bank_transaction_id', $paymentTwo->id)->firstOrFail();

        // Sammel-Rücklastschrift: EIN Ntry, zwei TxDtls mit eigenem Rückgabegrund.
        $returnTx = $this->importBatchTransaction('camt053_batch_return_sample.xml');
        $this->assertSame(TransactionDirection::Debit, $returnTx->direction);
        $this->assertTrue($returnTx->isReturnCandidate());
        $details = $returnTx->transactionDetails();
        $this->assertSame('AC04', $details[0]['return_reason']);
        $this->assertSame('MS03', $details[1]['return_reason']);

        // Die UI bietet die Kompensation je Einzeltransaktion an.
        $this->get(route('finance.reconciliation.show', $returnTx->statement->sqid))
            ->assertOk()
            ->assertSee(__('bank.split.return_title'));

        // Je Detail wird die passende Original-Zuordnung vorgeschlagen
        // (Betrag + EndToEndId + Mandat des Details).
        $origins = app(MatchingService::class)->suggestReturnOriginsForDetails($returnTx);
        $this->assertSame([0, 1], array_keys($origins));
        $this->assertTrue($origins[0][0]['allocation']->is($originalOne));
        $this->assertContains('amount', $origins[0][0]['reasons']);
        $this->assertContains('reference', $origins[0][0]['reasons']);
        $this->assertContains('mandate', $origins[0][0]['reasons']);
        $this->assertTrue($origins[1][0]['allocation']->is($originalTwo));

        // Verarbeitung je Zuordnung über den BESTEHENDEN processReturn-Pfad.
        app(ReconciliationService::class)->processReturn($returnTx, $originalOne, $details[0]['return_reason']);
        app(ReconciliationService::class)->processReturn($returnTx, $originalTwo, $details[1]['return_reason']);

        $this->assertSame(Invoice::STATUS_ISSUED, $first->refresh()->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $second->refresh()->status);
        $this->assertFalse($originalOne->refresh()->trashed());
        $this->assertFalse($originalTwo->refresh()->trashed());

        $compensations = PaymentAllocation::query()
            ->where('bank_transaction_id', $returnTx->id)
            ->where('kind', AllocationKind::Chargeback->value)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $compensations);
        $this->assertSame('-119.00', (string) $compensations[0]->amount);
        $this->assertStringContainsString('AC04', (string) $compensations[0]->note);
        $this->assertSame('-100.00', (string) $compensations[1]->amount);
        $this->assertStringContainsString('MS03', (string) $compensations[1]->note);
        $this->assertSame(MatchStatus::Matched, $returnTx->refresh()->match_status);
    }
}
