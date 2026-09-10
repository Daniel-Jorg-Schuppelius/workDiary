<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreditNoteLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeRecipientInvoiceLines;
use App\Services\Reselling\Register\{PeriodLinker, PeriodPlanner, RecipientReconciler};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gutschriften als negative Bezüge (Feature 152, Review 2026-09-10 A3):
 * Scope `issuedCreditNotes`, Leseseite `creditNotes()`, `PeriodLinker::attach()`
 * mit Vorzeichen aus dem Belegtyp, Abgleichseite mit Abschnitt „Gutschriften".
 */
class CreditNoteLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $premium;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-10');
        $this->premium = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-kl', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    private function subscription(): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'lexoffice_article_id' => $this->premium->id, 'quantity' => 1, 'starts_on' => '2025-08-05',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** Beleg mit EINER Lizenzposition („n Monat" des Premium-Artikels). */
    private function line(string $type, string $number, string $date, float $months, string $status = 'paid', bool $archived = false): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-kl', 'customer_id' => $this->customer->id,
            'voucher_type' => $type, 'voucher_status' => $status, 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR',
            'archived' => $archived, 'lines_synced_at' => now(),
        ]);
        $sign = $type === 'creditnote' ? -1 : 1;

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-bp', 'lexoffice_article_id' => $this->premium->id, 'name' => 'Microsoft 365 Business Premium',
            'quantity' => $months, 'unit_name' => 'Monat', 'unit_net' => '20.60', 'total_net' => (string) round($sign * 20.60 * $months, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_issued_credit_notes_scope_filters_type_status_and_contact(): void {
        $this->line('creditnote', 'GS/1', '2025-10-01', 6);
        $this->line('creditnote', 'GS/2', '2025-10-02', 6, 'draft');
        $this->line('creditnote', 'GS/3', '2025-10-03', 6, 'voided');
        $this->line('creditnote', 'GS/4', '2025-10-04', 6, 'paid', true);
        $this->line('invoice', 'RE/1', '2025-10-05', 12);

        $this->assertSame(['GS/1'], LexofficeVoucher::query()->issuedCreditNotes()->pluck('voucher_number')->all());
        $this->assertSame(['GS/1'], LexofficeVoucher::query()->issuedCreditNotes(['c-kl'])->pluck('voucher_number')->all());
        $this->assertSame([], LexofficeVoucher::query()->issuedCreditNotes(['c-other'])->pluck('voucher_number')->all());
        $this->assertSame(['RE/1'], LexofficeVoucher::query()->issuedInvoices()->pluck('voucher_number')->all(), 'Rechnungsscope bleibt ohne Gutschriften');

        $reader = new LexofficeRecipientInvoiceLines;
        $this->assertSame(['GS/1'], $reader->creditNotes($this->organization, ['c-kl'])->map(static fn(LexofficeVoucherLine $l): string => (string) $l->voucher->voucher_number)->all());
        $this->assertSame(['RE/1'], $reader->for($this->organization, ['c-kl'])->map(static fn(LexofficeVoucherLine $l): string => (string) $l->voucher->voucher_number)->all(), 'for() liefert weiterhin nur Rechnungen');
        $this->assertTrue($reader->creditNotes($this->organization, [])->isEmpty());
    }

    public function test_credit_note_link_is_negative_and_lowers_the_coverage(): void {
        $subscription = $this->subscription();
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $invoiceLine = $this->line('invoice', 'RE/2025/0820', '2025-10-14', 12);
        $creditLine = $this->line('creditnote', 'GS/2025/0007', '2026-02-01', 6);
        $linker = new PeriodLinker;

        $linker->attach($period, $invoiceLine, 12.0, null, null);
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);

        // Gutschrift über 6 Monate: Menge positiv eingegeben, Vorzeichen setzt der Linker.
        $link = $linker->attach($period, $creditLine, 6.0, 'Kündigung zum 31.01.', null);
        $this->assertSame('-6.00', $link->months);
        $this->assertSame('-0.500', $link->quantity);
        $this->assertSame('-123.60', $link->amount?->getAmount());
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $period->refresh();
        $this->assertSame(6.0, $period->coveredMonths(), '12 − 6');
        $this->assertSame(PeriodStatus::Partial, $period->status, 'Deckung sinkt: berechnet → teilweise');
        $this->assertSame(6.0, $period->openMonths());

        // Verbrauch der Gutschrift zählt betragsmäßig; ein zweiter (negativer) Aufruf ersetzt den Bezug.
        $this->assertSame(0.0, $linker->freeMonths($creditLine));
        $again = $linker->attach($period, $creditLine, -3.0, null, null);
        $this->assertSame($link->id, $again->id);
        $this->assertSame('-3.00', $again->months);
        $this->assertSame(3.0, $linker->freeMonths($creditLine));
        $this->assertSame(PeriodStatus::Partial, $period->fresh()?->status);
    }

    public function test_invoice_line_rejects_non_positive_months(): void {
        $period = $this->subscription()->periods()->orderBy('starts_on')->firstOrFail();
        $invoiceLine = $this->line('invoice', 'RE/2025/0820', '2025-10-14', 12);
        $linker = new PeriodLinker;

        try {
            $linker->attach($period, $invoiceLine, -3.0, null, null);
            $this->fail('negative Monate an einer Rechnungsposition müssen abgelehnt werden');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame((string) __('resale.credit_notes.error_positive'), $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $linker->attach($period, $invoiceLine, 0.0, null, null);
    }

    public function test_reconciler_lists_credit_notes_and_page_nets_them(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription();
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $invoiceLine = $this->line('invoice', 'RE/2025/0820', '2025-10-14', 12);
        $creditLine = $this->line('creditnote', 'GS/2025/0007', '2026-02-01', 6);
        (new PeriodLinker)->attach($period, $invoiceLine, 12.0, null, null);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $this->customer);
        $this->assertCount(1, $result['credit_notes']);
        $row = $result['credit_notes'][0];
        $this->assertSame($creditLine->id, $row['line']->id);
        $this->assertSame(6.0, $row['months']);
        $this->assertSame(0.0, $row['linked'], 'noch nicht verrechnet');
        $this->assertSame([], $row['periods']);
        $this->assertCount(1, $result['lines'], 'Gutschrift-Position gehört nicht in die Rechnungsliste');

        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk();
        $page->assertSee(__('resale.credit_notes.title'))->assertSee('GS/2025/0007')->assertSee(__('resale.credit_notes.unlinked'));

        // Verrechnen über das Abgleich-Formular: Lizenzen positiv, Bezug negativ.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $this->customer), [
            'period_id' => $period->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $creditLine->id), 'licences' => '1', 'per_licence' => '6.00',
        ])->assertRedirect(route('finance.resale.reconcile.show', $this->customer));

        $link = ResalePeriodLink::query()->where('linkable_id', $creditLine->id)->firstOrFail();
        $this->assertSame('-6.00', $link->months);
        $this->assertSame(PeriodStatus::Partial, $period->fresh()?->status);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $this->customer);
        $this->assertSame(6.0, $result['credit_notes'][0]['linked']);
        $this->assertCount(1, $result['credit_notes'][0]['periods']);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()->assertDontSee(__('resale.credit_notes.unlinked'));
    }
}
