<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalInsightsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Time\TimeEntry;
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Phase 154 (MVP-1242–1244): Auswertungen nur mit eigener Freigabe, Abschnitte
 * nur mit Bereichsfreigabe, Zahlen gleich der Liste, Jahresgrenze, Kunden- und
 * Organisationsgrenze, Gutschriften getrennt, Drilldowns, Startseiten-Zeile.
 */
class PortalInsightsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $worker;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Auswertungs-Kunde']);
        $this->portalUser = User::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $this->worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Auswertungs-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->portalUser->id,
        ]);
    }

    /**
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $extra
     */
    private function setVisibility(array $capabilities, string $timeDetail = 'entries', array $extra = []): void {
        $this->customer->forceFill(['portal_settings' => $extra + [
            'enabled' => true,
            'capabilities' => $capabilities,
            'time_detail' => $timeDetail,
            'time_scope' => 'published',
        ]])->save();
        $this->portalUser->unsetRelation('customer');
    }

    private function entry(string $date, int $minutes, ?Project $project = null): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => ($project ?? $this->project)->id,
            'user_id' => $this->worker->id,
            'date' => $date,
            'minutes' => $minutes,
            'description' => 'Eintrag',
            'customer_visible_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(string $number, string $issuedOn, string $total, array $overrides = []): Invoice {
        return Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'issued_on' => $issuedOn,
            'due_on' => CarbonImmutable::parse($issuedOn)->addDays(14)->toDateString(),
            'subtotal' => $total,
            'tax_rate' => '0.00',
            'tax_amount' => '0.00',
            'total' => $total,
        ], $overrides));
    }

    private function fakeExternal(ExternalInvoice ...$invoices): void {
        $this->app->instance(ExternalRevenue::class, new class(array_values($invoices)) implements ExternalRevenue {
            /** @param list<ExternalInvoice> $invoices */
            public function __construct(private readonly array $invoices) {}

            public function invoices(ExternalInvoiceQuery $query): array {
                return array_values(array_filter($this->invoices, fn (ExternalInvoice $i): bool => $i->customerId === $query->customerId));
            }

            public function invoicesByNumber(int $organizationId, array $numbers): array { return []; }

            public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array { return []; }

            public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array { return []; }

            public function overdueCount(int $organizationId, string $today): int { return 0; }

            public function productRevenue(CarbonInterface $from, CarbonInterface $to): array { return []; }

            public function customerIdsWithDocuments(int $organizationId): array { return []; }

            public function file(string $source, int $organizationId, string $key): ?ExternalInvoiceFile { return null; }

            public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile { return null; }
        });
    }

    private function external(string $number, int $customerId, string $issuedOn, float $gross, float $open, bool $credit = false, ?string $paidOn = null): ExternalInvoice {
        return new ExternalInvoice(
            source: 'lexoffice', key: 'k-' . $number, number: $number, customerId: $customerId, contactName: null,
            type: $credit ? 'creditnote' : 'invoice', status: $open > 0 ? InvoiceStatus::Issued : InvoiceStatus::Paid,
            issuedOn: CarbonImmutable::parse($issuedOn), dueOn: CarbonImmutable::parse($issuedOn)->addDays(14),
            paidOn: $paidOn !== null ? CarbonImmutable::parse($paidOn) : null,
            gross: $gross, net: $gross, open: $open, currency: 'EUR', url: null,
        );
    }

    public function test_insights_need_their_own_release(): void {
        $this->setVisibility(['time_entries', 'invoices']);
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.insights.index'))->assertNotFound();
        $this->get(route('customer.dashboard'))->assertOk()->assertDontSee(route('customer.insights.index'), false);
    }

    public function test_sections_follow_the_area_releases(): void {
        $this->setVisibility(['insights']);
        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.insights.index'))->assertOk();
        $page->assertSee(__('customer_portal.insights.empty_all'));
        $this->assertNull($page->viewData('time'));
        $this->assertNull($page->viewData('invoices'));
        $this->assertNull($page->viewData('tickets'));

        $this->setVisibility(['insights', 'time_entries'], 'summary');
        $page = $this->get(route('customer.insights.index'))->assertOk();
        $this->assertNotNull($page->viewData('time'));
        $this->assertNull($page->viewData('time')['stacked'], 'Summenstufe: kein Monat-nach-Projekt-Diagramm.');
        $this->assertNull($page->viewData('invoices'));
    }

    public function test_time_figures_match_the_list_and_respect_the_year_limit(): void {
        $second = Project::create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Zweites', 'status' => ProjectStatus::Active->value, 'created_by' => $this->portalUser->id]);
        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignProject = Project::create(['organization_id' => $this->organization->id, 'customer_id' => $foreignCustomer->id, 'name' => 'Fremd', 'status' => ProjectStatus::Active->value, 'created_by' => $this->portalUser->id]);
        $this->setVisibility(['insights', 'time_entries'], 'entries');
        $this->entry('2026-01-10', 60);
        $this->entry('2026-03-10', 90, $second);
        $this->entry('2025-03-10', 120);
        $this->entry('2019-03-10', 600);
        $this->entry('2026-03-11', 999, $foreignProject);
        TimeEntry::create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->worker->id, 'date' => '2026-04-01', 'minutes' => 30, 'description' => 'unveröffentlicht']);

        $this->actingAs($this->portalUser, 'customer');
        $page = $this->get(route('customer.insights.index'))->assertOk();
        $time = $page->viewData('time');
        $this->assertSame(150, $time['minutes']);
        $this->assertSame(120, $time['previous_minutes']);
        $this->assertSame(2, $time['entries']);
        $this->assertSame(2, $time['projects']);
        $this->assertSame(1.0, $time['monthly'][0]['y']);
        $this->assertSame(1.5, $time['monthly'][2]['y']);
        $this->assertSame(2.0, $time['monthly'][2]['compare']);
        $this->assertSame(route('customer.time-entries.index', ['year' => 2026, 'from' => '2026-03-01', 'to' => '2026-03-31']), $time['monthly'][2]['url']);
        $this->assertNotNull($time['stacked']);
        $this->assertSame([2026, 2025], $page->viewData('years'), 'Die Jahresgrenze der Zeiten gilt auch in der Auswertung.');

        $list = $this->get(route('customer.time-entries.index', ['year' => 2026]))->assertOk();
        $this->assertSame($time['minutes'], $list->viewData('kpis')['year_minutes']);

        $this->get(route('customer.insights.index', ['year' => 2019]))->assertOk()->assertViewHas('year', 2026);
    }

    public function test_invoice_figures_separate_credit_notes_and_count_only_the_customer(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->setVisibility(['insights', 'invoices']);
        $this->invoice('RE-1', '2026-02-05', '100.00', ['status' => InvoiceStatus::Paid, 'paid_on' => '2026-02-20']);
        $this->invoice('RE-2', '2026-05-05', '250.00');
        $this->invoice('GS-1', '2026-05-06', '50.00', ['type' => Invoice::TYPE_CREDIT_NOTE]);
        $this->invoice('RE-3', '2026-06-01', '999.00', ['status' => InvoiceStatus::Cancelled]);
        $this->invoice('RE-X', '2026-05-07', '777.00', ['customer_id' => $other->id]);
        $this->invoice('RE-OLD', '2025-11-01', '80.00');
        $this->fakeExternal(
            $this->external('LX-1', (int) $this->customer->id, '2026-03-15', 120.0, 120.0),
            $this->external('LX-2', (int) $other->id, '2026-03-16', 5000.0, 5000.0),
        );

        $this->actingAs($this->portalUser, 'customer');
        $page = $this->get(route('customer.insights.index', ['year' => 2026]))->assertOk();
        $block = $page->viewData('invoices')['currencies'][0];
        $this->assertSame('EUR', $block['currency']);
        $this->assertSame(3, $block['count']);
        $this->assertSame(470.0, $block['gross']);
        $this->assertSame(50.0, $block['credit']);
        $this->assertSame(370.0, $block['open']);
        $this->assertSame(370.0, $block['overdue']);
        $this->assertSame(250.0, $block['monthly'][4]['y']);
        $this->assertSame(50.0, $block['monthly'][4]['y2']);
        $this->assertSame(route('customer.invoices.index', ['year' => 2026, 'month' => 5]), $block['monthly'][4]['url']);
        // Offener Betrag je Monatsende: Februar bezahlt am 20.02. → Ende Februar 0, Ende März 120 (LX-1), Ende Mai 370 (+RE-2); RE-OLD aus 2025 bleibt offen.
        $this->assertSame(80.0, $block['open_trend'][0]['y']);
        $this->assertSame(80.0, $block['open_trend'][1]['y']);
        $this->assertSame(200.0, $block['open_trend'][2]['y']);
        $this->assertSame(450.0, $block['open_trend'][4]['y']);
        $this->assertSame([2026, 2025], $page->viewData('years'));
        $page->assertDontSee('RE-X')->assertDontSee('LX-2');
    }

    public function test_invoice_list_filters_by_year_status_and_month(): void {
        $this->setVisibility(['invoices']);
        $this->invoice('RE-1', '2026-02-05', '100.00', ['status' => InvoiceStatus::Paid, 'paid_on' => '2026-02-20']);
        $this->invoice('RE-2', '2026-05-05', '250.00');
        $this->invoice('RE-OLD', '2025-11-01', '80.00');

        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.invoices.index'))->assertOk()->assertSee('RE-1')->assertSee('RE-2')->assertDontSee('RE-OLD');
        $this->get(route('customer.invoices.index', ['year' => 2025]))->assertOk()->assertSee('RE-OLD')->assertDontSee('RE-2');
        $this->get(route('customer.invoices.index', ['status' => 'open']))->assertOk()->assertSee('RE-2')->assertDontSee('RE-1');
        $this->get(route('customer.invoices.index', ['month' => 2]))->assertOk()->assertSee('RE-1')->assertDontSee('RE-2');
        $this->get(route('customer.invoices.index', ['month' => 9]))->assertOk()->assertSee(__('customer_portal.invoices_list.empty_filtered'));
    }

    public function test_ticket_section_counts_own_tickets_and_hides_average_below_threshold(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->setVisibility(['insights', 'tickets']);
        ServiceTicket::factory()->count(2)->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'reported_at' => '2026-03-02 09:00:00']);
        ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'reported_at' => '2026-04-02 09:00:00', 'resolved_at' => '2026-04-04 09:00:00', 'status' => ServiceTicketStatus::Done->value]);
        ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $other->id, 'reported_at' => '2026-04-02 09:00:00']);

        $this->actingAs($this->portalUser, 'customer');
        $tickets = $this->get(route('customer.insights.index'))->assertOk()->viewData('tickets');
        $this->assertSame(3, $tickets['opened']);
        $this->assertSame(1, $tickets['resolved']);
        $this->assertNull($tickets['average_days']);
        $this->assertSame(2, $tickets['series'][2]['opened']);
        $this->assertSame(1, $tickets['series'][3]['resolved']);
    }

    public function test_dashboard_row_appears_only_with_the_release(): void {
        $this->setVisibility(['time_entries', 'invoices']);
        $this->entry('2026-01-10', 60);
        $this->invoice('RE-2', '2026-05-05', '250.00');
        $this->fakeExternal();

        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.dashboard'))->assertOk()->assertDontSee(__('customer_portal.insights.dashboard_title', ['year' => 2026]));

        $this->setVisibility(['time_entries', 'invoices', 'insights']);
        $page = $this->get(route('customer.dashboard'))->assertOk();
        $page->assertSee(__('customer_portal.insights.dashboard_title', ['year' => 2026]))
            ->assertSee(route('customer.insights.index', ['year' => 2026]), false);
        $this->assertSame(60, $page->viewData('insights')['minutes']);
        $this->assertSame(250.0, $page->viewData('insights')['gross']);
    }

    public function test_insights_of_another_organization_are_not_visible(): void {
        $this->setVisibility(['insights', 'invoices']);
        $foreignOrg = \App\Models\Platform\Organization::factory()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);
        Invoice::withoutGlobalScopes()->create([
            'organization_id' => $foreignOrg->id, 'customer_id' => $foreignCustomer->id, 'number' => 'FREMD-1', 'status' => InvoiceStatus::Issued,
            'currency' => 'EUR', 'issued_on' => '2026-05-05', 'subtotal' => '10.00', 'tax_rate' => '0.00', 'tax_amount' => '0.00', 'total' => '10.00',
        ]);
        $this->fakeExternal();

        $this->actingAs($this->portalUser, 'customer');
        $page = $this->get(route('customer.insights.index'))->assertOk();
        $page->assertDontSee('FREMD-1');
        $this->assertSame([], $page->viewData('invoices')['currencies']);
    }
}
