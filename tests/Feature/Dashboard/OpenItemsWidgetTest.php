<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemsWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Dashboard\Widgets\OpenItemsWidget;
use App\Enums\Finance\{AccountingSovereignty, OpenItemDirection, ProfitDetermination};
use App\Models\Platform\User;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Accounting\AccountingProfileService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Kachel „Offene Posten“ ohne lokale Buchhaltung aus dem Buchhaltungsprogramm (MVP-1125). */
final class OpenItemsWidgetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        foreach ([
            ['v-out', 'invoice', 'open', '119.00', '119.00', now()->subDays(5)],
            ['v-out-late', 'invoice', 'open', '238.00', '38.00', now()->addDays(5)],
            ['v-in', 'purchaseinvoice', 'open', '50.00', '50.00', now()->subDays(1)],
            ['v-in-paid', 'purchaseinvoice', 'paid', '70.00', '0.00', now()->subDays(1)],
        ] as [$id, $type, $status, $total, $open, $due]) {
            LexofficeVoucher::query()->create([
                'organization_id' => $this->organization->id, 'external_id' => $id, 'voucher_type' => $type, 'voucher_status' => $status,
                'voucher_number' => strtoupper($id), 'voucher_date' => now()->subDays(20)->toDateString(), 'due_date' => $due->toDateString(),
                'total_amount' => $total, 'open_amount' => $open, 'currency' => 'EUR',
            ]);
        }
    }

    private function render(): View {
        $view = app(OpenItemsWidget::class)->render($this->admin);
        $this->assertInstanceOf(View::class, $view);

        return $view;
    }

    public function test_without_local_ledger_the_tile_reads_the_accounting_program(): void {
        $view = $this->render();

        $this->assertTrue($view->getData()['external']);
        $rows = collect($view->getData()['rows'])->keyBy(static fn (object $row): string => $row->direction->value);
        $this->assertEqualsWithDelta(157.0, $rows[OpenItemDirection::Receivable->value]->total, 0.001);
        $this->assertEqualsWithDelta(119.0, $rows[OpenItemDirection::Receivable->value]->overdue, 0.001);
        $this->assertEqualsWithDelta(50.0, $rows[OpenItemDirection::Payable->value]->total, 0.001);
        $this->assertEqualsWithDelta(50.0, $rows[OpenItemDirection::Payable->value]->overdue, 0.001);
        $this->assertStringContainsString(__('dashboard.widget.open_items.external_hint'), $view->render());
    }

    public function test_with_local_ledger_the_tile_stays_on_the_ledger(): void {
        app(AccountingProfileService::class)->configure($this->organization, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => CarbonImmutable::create(2026, 1, 1),
            'note' => null,
        ])->forceFill(['sovereignty' => AccountingSovereignty::Local])->save();

        $view = $this->render();

        $this->assertFalse($view->getData()['external']);
        $this->assertCount(0, $view->getData()['rows']);
    }
}
