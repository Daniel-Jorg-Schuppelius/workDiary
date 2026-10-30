<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherLinesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\{AccountingVoucherState, BillingMode};
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer\Customer;
use App\Models\Finance\{AccountingVoucher, AccountingVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucherLine, VoucherMirror};
use App\Services\Reselling\Mirror\{AccountingVoucherMirrorSource, InvoiceMirror};
use App\Services\Reselling\Plan\{AccountingRecurringPlanSource, RecurringPlans};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Positionen im allgemeinen Belegspiegel (Feature 185, MVP-1424/1426):
 * Speichern in place, Spiegelquelle für das Register (Vorschlagslauf mit
 * automatischer Bestätigung), Serienvorlagen als Plan-Quelle.
 */
class AccountingVoucherLinesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH', 'billing_mode' => BillingMode::SevDesk]);
    }

    private function voucher(string $externalId, string $number, string $date, DocumentKind $kind = DocumentKind::Invoice, AccountingVoucherState $state = AccountingVoucherState::Open): AccountingVoucher {
        return AccountingVoucher::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => 'sevdesk', 'external_id' => $externalId, 'customer_id' => $this->customer->id,
            'direction' => $kind === DocumentKind::RecurringTemplate ? DocumentDirection::Neutral->value : DocumentDirection::Outgoing->value, 'document_kind' => $kind->value,
            'voucher_state' => $state, 'voucher_number' => $number, 'voucher_date' => $date, 'currency' => 'EUR', 'synced_at' => now(),
            'payload' => $kind === DocumentKind::RecurringTemplate ? ['plan' => ['status' => 'ACTIVE', 'interval' => 'ANNUALLY', 'next_execution_on' => '2026-09-03', 'starts_on' => '2025-09-03']] : [],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'quantity' => 2, 'starts_on' => '2025-09-03', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
            'article_ref' => 'sevdesk:MS-BP', // Artikelschlüssel der Spiegelposition (<plugin>:<Fremd-ID>) — Voraussetzung für „sicher“
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_store_lines_upserts_in_place_and_stamps_the_voucher(): void {
        $voucher = $this->voucher('invoice:1', 'RE-1', '2026-09-03');
        $mirror = app(VoucherMirror::class);
        $this->assertSame(0, $mirror->storeLines($this->organization->id, 'sevdesk', 'invoice:unbekannt', []), 'ohne Beleg nichts');

        $count = $mirror->storeLines($this->organization->id, 'sevdesk', 'invoice:1', [
            new MirroredVoucherLine(1, 'Microsoft 365 Business Premium', 2.0, '247.2000', '494.40', 'EUR', 'MS-BP', null, 'Jahreslizenz', 'Stück', '19'),
            new MirroredVoucherLine(2, 'Business Support', 1.0, '505.6000', '505.60'),
        ]);
        $this->assertSame(2, $count);
        $this->assertNotNull($voucher->fresh()?->lines_synced_at);
        $this->assertSame(['Microsoft 365 Business Premium', 'Business Support'], $voucher->lines()->pluck('name')->all());

        // Zweiter Lauf: Position 1 geändert, Position 2 verschwunden.
        $mirror->storeLines($this->organization->id, 'sevdesk', 'invoice:1', [new MirroredVoucherLine(1, 'Microsoft 365 Business Premium', 3.0, '247.2000', '741.60', 'EUR', 'MS-BP')]);
        $lines = $voucher->lines()->get();
        $this->assertCount(1, $lines);
        $this->assertSame('3.000', $lines[0]->quantity);
        $this->assertSame(1, AccountingVoucherLine::query()->count());
    }

    public function test_mirror_source_serves_licence_lines_and_the_proposal_run_confirms_a_sure_match(): void {
        $subscription = $this->subscription();
        $voucher = $this->voucher('invoice:1', 'RE-1', '2026-09-03');
        app(VoucherMirror::class)->storeLines($this->organization->id, 'sevdesk', 'invoice:1', [
            new MirroredVoucherLine(1, 'Microsoft 365 Business Premium', 2.0, '247.2000', '494.40', 'EUR', 'MS-BP', null, 'Jahreslizenz', 'Stück', '19'),
            new MirroredVoucherLine(2, 'Wartung Serverraum', 1.0, '90.0000', '90.00'),
        ]);
        $draft = $this->voucher('invoice:2', 'RE-2', '2026-09-03', DocumentKind::Invoice, AccountingVoucherState::Draft);
        app(VoucherMirror::class)->storeLines($this->organization->id, 'sevdesk', 'invoice:2', [new MirroredVoucherLine(1, 'Microsoft 365 Business Premium', 2.0, '247.2000', '494.40')]);

        $source = app(AccountingVoucherMirrorSource::class);
        $this->assertSame((new AccountingVoucherLine)->getTable(), $source->morphClass());
        $this->assertTrue($source->coversRecipient($this->organization, $this->customer), 'gespiegelter Ausgangsbeleg des Kunden');
        $lines = app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id]);
        $this->assertCount(1, $lines, 'nur die Lizenzposition der gültigen Rechnung — kein Entwurf, keine Wartung');
        $line = $lines->first();
        $this->assertSame(AccountingVoucherMirrorSource::KEY, $line?->sourceKey);
        $this->assertSame('RE-1', $line?->voucherNumber);
        $this->assertSame('invoice:1', $line?->voucherExternalId);
        $this->assertSame($this->customer->id, $line?->recipientCustomerId);
        $this->assertSame(2.0, $line?->quantity);
        $this->assertSame(0, app(InvoiceMirror::class)->pendingCount($this->organization, [$this->customer->id]), 'beide Belege tragen lines_synced_at');
        $this->voucher('invoice:3', 'RE-3', '2026-09-04');
        $this->assertSame(1, app(InvoiceMirror::class)->pendingCount($this->organization, [$this->customer->id]), 'RE-3 ohne Positionen zählt als offen');
        $this->assertSame(1, $source->unlinkedLines($this->organization, 10)['total']);
        $this->assertSame($line?->morphId, $source->lineByKey($this->organization, (string) $line?->key)?->morphId);

        // Vorschlagslauf: die Rechnung vom 03.09. deckt das Lizenzjahr 2026/27 — sicherer Treffer, automatisch bestätigt.
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['auto_confirmed']);
        $period = $subscription->periods()->where('starts_on', '2026-09-03')->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $link = ResalePeriodLink::query()->where('period_id', $period->id)->firstOrFail();
        $this->assertSame(LinkOrigin::Auto, $link->origin);
        $this->assertSame($source->morphClass(), $link->linkable_type);
        $this->assertSame(0, $source->unlinkedLines($this->organization, 10)['total']);
        $this->assertTrue($source->draftBecameInvoice($this->organization, 'invoice:1'));
        $this->assertFalse($source->draftBecameInvoice($this->organization, 'invoice:2'), 'Entwurf bleibt Entwurf');
        $this->assertFalse($draft->fresh()?->lines_synced_at === null);
    }

    public function test_recurring_templates_in_the_mirror_are_a_plan_source(): void {
        $template = $this->voucher('recurring:7', 'Serie Badhaus', '2025-09-03', DocumentKind::RecurringTemplate, AccountingVoucherState::Draft);
        app(VoucherMirror::class)->storeLines($this->organization->id, 'sevdesk', 'recurring:7', [new MirroredVoucherLine(1, 'Microsoft 365 Business Premium', 2.0, '247.2000', '494.40', 'EUR', 'MS-BP')]);

        $plans = app(RecurringPlans::class);
        $this->assertTrue($plans->availableFor($this->organization));
        $templates = $plans->templatesFor($this->organization);
        $this->assertCount(1, $templates);
        $plan = $templates->first();
        $this->assertSame(AccountingRecurringPlanSource::KEY, $plan?->sourceKey);
        $this->assertSame('accounting:sevdesk:recurring:7', $plan?->key);
        $this->assertSame('Serie Badhaus', $plan?->title);
        $this->assertSame($this->customer->id, $plan?->recipientCustomerId);
        $this->assertTrue($plan?->isActive() ?? false);
        $this->assertSame(12, $plan?->intervalMonths());
        $this->assertSame('2026-09-03', $plan?->nextExecutionOn?->toDateString());
        $this->assertCount(1, $plan?->licenceLines() ?? []);
        $this->assertSame($plan?->key, $plans->templateByKey($this->organization, 'accounting:sevdesk:recurring:7')?->key);
        $this->assertNull($plans->templateByKey($this->organization, 'accounting:sevdesk:recurring:99'));
        $this->assertTrue($plans->coversRecipient($this->organization, $this->customer));
        // Serienvorlagen sind kein Umsatz: die Spiegelquelle für Rechnungen sieht sie nicht.
        $this->assertCount(0, app(InvoiceMirror::class)->linesFor($this->organization, [$this->customer->id]));
        $this->assertSame(DocumentKind::RecurringTemplate, DocumentKind::from((string) $template->document_kind));
    }
}
