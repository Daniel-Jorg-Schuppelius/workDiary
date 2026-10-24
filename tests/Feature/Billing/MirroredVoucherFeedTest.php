<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucherFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Services\Finance\Accounting\Vouchers\{AccountingVoucherPurchaseSource, AccountingVoucherRevenueSource};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gespiegelte Belege im Belegfluss: im Fremdsystem gelöschte zählen in keiner
 * Kennzahl und erscheinen nur mit den archivierten, ein Betrag in zweiter
 * Währung reist mit der Zeile, und ein Filter ohne passende Quelle ergibt eine
 * leere Liste statt eines Fehlers.
 */
class MirroredVoucherFeedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(array $attributes): AccountingVoucher {
        return AccountingVoucher::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'akaunting',
            'voucher_type' => 'invoice',
            'direction' => DocumentDirection::Outgoing->value,
            'document_kind' => DocumentKind::Invoice->value,
            'voucher_state' => AccountingVoucherState::Open,
            'voucher_date' => '2026-08-10',
        ]);
    }

    /** @param array<string, bool> $sources */
    private function filters(bool $archived = false, ?DocumentOrigin $origin = null, array $sources = ['voucher' => true]): DocumentFeedFilters {
        return new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            origin: $origin,
            includeArchived: $archived,
            sources: $sources,
        );
    }

    /** @return array<string, mixed> */
    private function euroTotals(DocumentFeedFilters $filters): array {
        return collect((new DocumentFeedQuery($filters))->totals())->firstWhere('currency', 'EUR') ?? [];
    }

    public function test_a_voucher_deleted_in_the_source_leaves_every_sum_and_shows_only_with_archived(): void {
        $this->voucher(['voucher_number' => 'INV-1', 'total_amount' => '100.00', 'open_amount' => '100.00']);
        $this->voucher(['voucher_number' => 'INV-2', 'total_amount' => '50.00', 'open_amount' => '50.00', 'source_deleted_at' => now()]);

        $rows = (new DocumentFeedQuery($this->filters()))->paginate(25, 'doc_date', 'desc');
        $this->assertSame(['INV-1'], collect($rows->items())->pluck('number')->all());
        $this->assertSame([100.0, 100.0], [(float) $this->euroTotals($this->filters())['revenue'], (float) $this->euroTotals($this->filters())['open']]);

        $archived = collect((new DocumentFeedQuery($this->filters(archived: true)))->paginate(25, 'doc_date', 'desc')->items());
        $this->assertSame('deleted', $archived->firstWhere('number', 'INV-2')?->state);
        $totals = $this->euroTotals($this->filters(archived: true));
        $this->assertSame([100.0, 100.0], [(float) $totals['revenue'], (float) $totals['open']], 'Auch mit den archivierten zählt der gelöschte Beleg nicht.');
    }

    public function test_the_amount_in_a_second_currency_reaches_the_feed(): void {
        $this->voucher(['voucher_number' => 'EXP-1', 'total_amount' => '50.00', 'currency' => 'USD', 'foreign_amount' => '46.25', 'foreign_currency' => 'EUR']);

        $row = collect((new DocumentFeedQuery($this->filters()))->paginate(25, 'doc_date', 'desc')->items())->firstWhere('number', 'EXP-1');
        $this->assertSame(['46.25', 'EUR'], [(string) $row?->foreign_amount, $row?->foreign_currency]);

        $this->actingAs($this->admin)
            ->withSession(['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-08-01', 'ui.daterange.to' => '2026-08-31'])
            ->get(route('billing.feed'))
            ->assertOk()
            ->assertSee('46,25 EUR');
    }

    public function test_a_filter_no_source_serves_yields_an_empty_list(): void {
        // Nur gespiegelte Belege sichtbar, aber Herkunft „lokal“: keine Quelle liefert ein Sub-Select.
        $query = new DocumentFeedQuery($this->filters(origin: DocumentOrigin::Local));

        $this->assertSame(0, $query->paginate(25, 'doc_date', 'desc')->total());
        $this->assertSame([], $query->totals());
    }

    public function test_revenue_and_purchases_skip_vouchers_deleted_in_the_source(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->voucher(['voucher_number' => 'INV-3', 'total_amount' => '10.00']);
        $deleted = $this->voucher(['voucher_number' => 'INV-4', 'total_amount' => '20.00', 'source_deleted_at' => now()]);
        $this->voucher(['voucher_number' => 'BILL-1', 'voucher_type' => 'bill', 'direction' => DocumentDirection::Incoming->value, 'supplier_id' => $supplier->id, 'total_amount' => '5.00']);
        $this->voucher(['voucher_number' => 'BILL-2', 'voucher_type' => 'bill', 'direction' => DocumentDirection::Incoming->value, 'supplier_id' => $supplier->id, 'total_amount' => '7.00', 'source_deleted_at' => now()]);

        $revenue = app(AccountingVoucherRevenueSource::class);
        $this->assertSame(['INV-3'], $revenue->revenueVouchers()->where('organization_id', $this->organization->id)->pluck('voucher_number')->all());
        $this->assertTrue($revenue->revenueVouchers(withCancelled: true)->whereKey($deleted->id)->exists(), 'Wie stornierte nur auf ausdrücklichen Wunsch, etwa für Provisionsnachweise.');
        $this->assertCount(1, app(AccountingVoucherPurchaseSource::class)->purchases(null, null, [(int) $supplier->id]));
    }
}
