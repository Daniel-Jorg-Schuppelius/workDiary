<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankProvisionalAndChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BankStatementFormat, StatementChainCheck};
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\User;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Finance\{BankImportException, BankImportService, ReconciliationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Vorläufige Auszüge CAMT.052/054 und PAIN (MVP-1274), Saldenkette zwischen Auszügen (MVP-1279). */
final class BankProvisionalAndChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization();
        $this->actingAs(User::factory()->admin()->create(['organization_id' => $this->organization->id]));
    }

    private function import(string $fixture): BankStatement {
        return app(BankImportService::class)->importContent((string) file_get_contents(base_path('tests/Fixtures/finance/' . $fixture)), $fixture, $this->organization->id)[0];
    }

    private function camt053(string $id, string $from, string $to, string $opening, string $closing, string $amount = '119.00'): string {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08"><BkToCstmrStmt>
            <GrpHdr><MsgId>{$id}</MsgId><CreDtTm>{$to}T23:59:00+02:00</CreDtTm></GrpHdr>
            <Stmt><Id>{$id}</Id><Acct><Id><IBAN>DE89370400440532013000</IBAN></Id><Ccy>EUR</Ccy></Acct>
            <Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">{$opening}</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>{$from}</Dt></Dt></Bal>
            <Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">{$closing}</Amt><CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>{$to}</Dt></Dt></Bal>
            <Ntry><Amt Ccy="EUR">{$amount}</Amt><CdtDbtInd>CRDT</CdtDbtInd><Sts><Cd>BOOK</Cd></Sts><BookgDt><Dt>{$from}</Dt></BookgDt><ValDt><Dt>{$from}</Dt></ValDt>
            <NtryDtls><TxDtls><Refs><EndToEndId>E2E-{$id}</EndToEndId></Refs><Amt Ccy="EUR">{$amount}</Amt><RmtInf><Ustrd>Zahlung {$id}</Ustrd></RmtInf></TxDtls></NtryDtls></Ntry>
            </Stmt></BkToCstmrStmt></Document>
            XML;
    }

    public function test_intraday_report_and_notification_are_provisional_until_the_statement_replaces_them(): void {
        $intraday = $this->import('camt052_sample.xml');
        $notification = $this->import('camt054_sample.xml');

        $this->assertSame(BankStatementFormat::Camt052, $intraday->source_format);
        $this->assertSame(BankStatementFormat::Camt054, $notification->source_format);
        $this->assertTrue($intraday->is_provisional);
        $this->assertTrue($notification->is_provisional);
        $this->assertSame(2, BankTransaction::query()->count());
        $this->assertSame(0, BankTransaction::query()->booked()->count());

        // Vorläufige Umsätze werden weder abgeglichen noch gebucht.
        $transaction = $intraday->transactions()->firstOrFail();
        try {
            app(ReconciliationService::class)->ignore($transaction);
            $this->fail('Vorläufiger Umsatz wurde abgeglichen.');
        } catch (BankImportException $e) {
            $this->assertSame('provisional', $e->reason);
        }
        $this->get(route('finance.reconciliation.show', $intraday))->assertOk()
            ->assertSee(__('bank.statement.provisional_hint', ['format' => BankStatementFormat::Camt052->label()]))
            ->assertDontSee(route('finance.reconciliation.ignore', $transaction));
        $this->post(route('finance.reconciliation.ignore', $transaction))->assertForbidden();

        $final = $this->import('camt053_sample.xml');
        $this->assertFalse($final->is_provisional);
        $this->assertNotNull($intraday->fresh()->superseded_at);
        $this->assertNotNull($notification->fresh()->superseded_at);
        $this->assertSame($final->transactions()->count(), BankTransaction::query()->booked()->count());
        $this->get(route('finance.reconciliation.index'))->assertOk()->assertSee(__('bank.statement.superseded'));
    }

    public function test_pain_orders_are_announced_and_provisional(): void {
        $statement = $this->import('pain001_sample.xml');

        $this->assertSame(BankStatementFormat::Pain001, $statement->source_format);
        $this->assertTrue($statement->is_provisional);
        $this->assertSame(0, BankTransaction::query()->booked()->count());
    }

    public function test_balance_chain_between_consecutive_statements(): void {
        $service = app(BankImportService::class);
        $first = $service->importContent($this->camt053('A', '2026-05-01', '2026-05-31', '1000.00', '1119.00'), 'a.xml', $this->organization->id)[0];
        $second = $service->importContent($this->camt053('B', '2026-06-01', '2026-06-30', '1119.00', '1238.00'), 'b.xml', $this->organization->id)[0];
        $gap = $service->importContent($this->camt053('C', '2026-08-01', '2026-08-31', '1238.00', '1357.00'), 'c.xml', $this->organization->id)[0];
        $mismatch = $service->importContent($this->camt053('D', '2026-09-01', '2026-09-30', '1300.00', '1419.00'), 'd.xml', $this->organization->id)[0];

        $this->assertSame(StatementChainCheck::First, $first->chain_check);
        $this->assertSame(StatementChainCheck::Ok, $second->chain_check);
        $this->assertSame(StatementChainCheck::Gap, $gap->chain_check);
        $this->assertSame(StatementChainCheck::Mismatch, $mismatch->chain_check);
        $this->get(route('finance.reconciliation.index'))->assertOk()->assertSee(StatementChainCheck::Gap->label());
    }
}
