<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Numbering\NumberScope;
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Mail\InvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceMailTemplate};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\TestCase;

class InvoiceTest extends TestCase {
    use WithPluginSecrets;
    /** D12: deterministische Nummern statt random_int (Unique-Kollisionsschutz bleibt). */
    private static int $invoiceNo = 0;

    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Feature 105: die Rechnungsliste ist im Belegfluss aufgegangen. Der Feed
     * selbst ist erreichbar (jeder sieht dort mindestens seine eigenen
     * Auslagen) — Rechnungszeilen zeigt er aber nur mit dem Recht dafür.
     */
    public function test_index_hides_invoices_without_billing_role(): void {
        $regular = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0999',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($regular)->get(route('billing.feed'))
            ->assertOk()
            ->assertDontSee('R2030-0999');
        $this->actingAs($regular)->get(route('invoices.index'))
            ->assertRedirect(route('billing.feed', ['tab' => 'outgoing']));
        $this->getAsAdmin('billing.feed')->assertOk()->assertSee('R2030-0999');
    }

    public function test_create_invoice_from_time_entries(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('180.00', $invoice->subtotal?->getAmount());
        $this->assertSame('34.20', $invoice->tax_amount?->getAmount());
        $this->assertSame('214.20', $invoice->total?->getAmount());
    }

    public function test_billing_increment_and_grouping_consolidate_entries(): void {
        $this->project->update([
            'billing_increment_minutes' => 15,
            'billing_grouping_gap_minutes' => 15,
        ]);

        // 10:00–10:30 (30 Min) + Lücke 10 + 10:40–11:00 (20 Min) ⇒ ein Block,
        // 60 Min gearbeitet+überbrückt ⇒ aufgerundet 60 Min = 1,0 h.
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'started_at' => '2030-04-01 10:00',
            'ended_at' => '2030-04-01 10:30',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'started_at' => '2030-04-01 10:40',
            'ended_at' => '2030-04-01 11:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(1, $invoice->items()->count());

        /** @var \App\Models\Invoicing\InvoiceItem $item */
        $item = $invoice->items()->first();
        $this->assertSame('1.000', $item->quantity);
        $this->assertSame('90.0000', $item->unit_price?->getAmount());
        $this->assertSame('90.00', $item->amount?->getAmount());
        $this->assertSame(2, $item->timeEntries()->count());
        $this->assertSame('90.00', $invoice->subtotal?->getAmount());
    }

    public function test_single_service_date_is_set_and_not_a_period(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $this->assertFalse($invoice->hasServicePeriod());
        $this->assertSame('2030-04-01', $invoice->serviceDateSingle()?->toDateString());
        $this->assertSame('2030-04-01', $invoice->items()->firstOrFail()->service_date?->toDateString());
    }

    public function test_multiple_days_form_a_service_period_with_per_item_dates(): void {
        foreach (['2030-04-01', '2030-04-03'] as $day) {
            TimeEntry::create([
                'organization_id' => $this->organization->id,
                'project_id' => $this->project->id,
                'user_id' => $this->admin->id,
                'date' => $day,
                'minutes' => 60,
                'kind' => TimeEntryKind::Work->value,
                'billable' => true,
                'hourly_rate' => '90.00',
            ]);
        }

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $this->assertTrue($invoice->hasServicePeriod());
        $this->assertNull($invoice->serviceDateSingle());
        $this->assertSame('2030-04-01', $invoice->serviceDateFrom()?->toDateString());
        $this->assertSame('2030-04-03', $invoice->serviceDateTo()?->toDateString());
        $this->assertSame(2, $invoice->items()->whereNotNull('service_date')->count());
    }

    public function test_material_is_billed_as_separate_invoice_with_delivery_dates(): void {
        $sheet = \App\Models\Time\Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project->value,
            'work_date' => '2030-05-10',
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft->value,
        ]);
        \App\Models\Material\MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $sheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'material',
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $this->assertSame(Invoice::CATEGORY_MATERIAL, $invoice->category);
        $this->assertTrue($invoice->isMaterial());
        $this->assertSame(__('Lieferdatum'), $invoice->dateLabelSingle());
        $this->assertSame(1, $invoice->items()->count());

        /** @var \App\Models\Invoicing\InvoiceItem $item */
        $item = $invoice->items()->first();
        $this->assertSame('2030-05-10', $item->service_date?->toDateString());
        $this->assertSame('30.00', $item->amount?->getAmount());
        $this->assertSame('2030-05-10', $invoice->serviceDateSingle()?->toDateString());

        // Material ist als abgerechnet markiert ⇒ keine Doppelberechnung.
        $this->assertTrue(\App\Models\Material\MaterialUsage::firstOrFail()->billed);

        // Zweiter Lauf bricht ab (nichts Offenes mehr): keine leere Rechnung,
        // keine verbrauchte Nummer — Fehler auf customer_id (Sweep 2026-07-10).
        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'material',
        ])->assertSessionHasErrors('customer_id');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_material_invoice_line_carries_the_article_of_the_material(): void {
        $article = \App\Models\Article\Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kabel NYM']);
        $material = \App\Models\Material\Material::create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'name' => 'Kabel NYM', 'unit' => 'm']);
        $sheet = \App\Models\Time\Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project->value,
            'work_date' => '2030-05-10',
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft->value,
        ]);
        foreach ([$material->id, null] as $materialId) {
            \App\Models\Material\MaterialUsage::create([
                'organization_id' => $this->organization->id,
                'timesheet_id' => $sheet->id,
                'material_id' => $materialId,
                'description' => 'Kabel',
                'quantity' => '2.000',
                'unit' => 'm',
                'unit_price' => '5.0000',
            ]);
        }

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'material',
        ])->assertRedirect();

        $articleIds = Invoice::firstOrFail()->items()->orderBy('position')->pluck('article_id')->all();
        $this->assertSame([$article->id, null], $articleIds);
    }

    public function test_travel_charge_added_to_service_invoice_and_not_doubled(): void {
        config()->set('travel.enabled', true);
        config()->set('travel.mode', 'flat');
        config()->set('travel.flat_amount', 20);

        $this->tourToCustomer('2030-04-01');
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'service',
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $travel = $invoice->items->firstWhere('tour_id', '!=', null);
        $this->assertNotNull($travel);
        $this->assertSame('20.00', $travel->amount?->getAmount());
        $this->assertStringContainsString('Anfahrt', $travel->description);
        $this->assertTrue(\App\Models\Diary\Tour::firstOrFail()->travel_billed);

        // Zweite Generierung: nichts Offenes mehr (Zeit exportiert, Anfahrt
        // abgerechnet) → Abbruch statt leerer Rechnung (Whitebox 2026-07-10).
        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'service',
        ])->assertSessionHasErrors('customer_id');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_pure_material_day_puts_travel_on_material_invoice(): void {
        config()->set('travel.enabled', true);
        config()->set('travel.mode', 'flat');
        config()->set('travel.flat_amount', 15);

        $this->tourToCustomer('2030-05-10'); // keine Zeiteinträge an dem Tag
        $sheet = \App\Models\Time\Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project->value,
            'work_date' => '2030-05-10',
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft->value,
        ]);
        \App\Models\Material\MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $sheet->id,
            'description' => 'Kabel',
            'quantity' => '1.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'material',
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $travel = $invoice->items->firstWhere('tour_id', '!=', null);
        $this->assertNotNull($travel);
        $this->assertSame('15.00', $travel->amount?->getAmount());
        $this->assertTrue(\App\Models\Diary\Tour::firstOrFail()->travel_billed);
    }

    private function tourToCustomer(string $date): \App\Models\Diary\Tour {
        $tour = \App\Models\Diary\Tour::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'tour_date' => $date,
            'status' => \App\Enums\Tour\TourStatus::Completed->value,
            'planned_distance_km' => '0',
            'planned_duration_minutes' => 0,
        ]);
        \App\Models\Diary\DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'tour_id' => $tour->id,
            'tour_position' => 1,
        ]);

        return $tour;
    }

    public function test_foreign_customer_appears_in_booking_line(): void {
        $foreign = \App\Models\Customer\ForeignCustomer::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Thieme',
        ]);
        $this->project->update(['foreign_customer_id' => $foreign->id]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        // Ohne Endkunden-Filter ("alle Endkunden") — der Endkunde muss dennoch
        // je Buchungszeile auftauchen.
        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'content' => 'service',
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail()->load('items');
        $this->assertStringContainsString('Endkunde Thieme', (string) $invoice->items()->firstOrFail()->description);
    }

    public function test_index_accepts_numeric_customer_filter_fallback(): void {
        $otherCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Other GmbH',
            'currency' => 'EUR',
            'hourly_rate' => '80.00',
            'created_by' => $this->admin->id,
        ]);

        $invoiceA = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0101',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'number' => 'R2030-0102',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        // Feature 105: gefiltert wird im Belegfluss; numerische Alt-Links
        // bleiben neben dem Sqid gültig.
        $this->getAsAdmin('billing.feed', ['customer' => (string) $this->customer->id])
            ->assertOk()
            ->assertViewHas('rows', static function ($rows) use ($invoiceA): bool {
                $items = $rows->items();

                return count($items) === 1
                    && $items[0]->source_type === 'invoice'
                    && (int) $items[0]->source_id === (int) $invoiceA->id;
            });
    }

    public function test_issue_and_pay_workflow(): void {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0001',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1]);

        $this->postAsAdmin('invoices.issue', [], $invoice)->assertRedirect();
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()?->status);

        $this->postAsAdmin('invoices.pay', [], $invoice)->assertRedirect();
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_pdf_export(): void {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0002',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'h',
            'unit_price' => '90.00',
            'position' => 1,
        ]);

        $response = $this->getAsAdmin('invoices.pdf', $invoice);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_publish_to_lexoffice_sends_payload_and_updates_invoice(): void {
        $this->pluginSecret('lexoffice', ['enabled' => true, 'api_key' => 'test-key']);
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::Invoice, ['source' => 'lexoffice']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => NumberSequenceService::DRAFT_PREFIX . 'LEX00001',
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'description' => 'Beratung',
            'quantity' => '3.00',
            'unit' => 'h',
            'unit_price' => '95.00',
            'position' => 1,
        ]);

        \Tests\Support\FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?finalize=true' => \Tests\Support\FakePluginHttp::response([
                'id' => 'lex-inv-1',
                'resourceUri' => 'https://api.lexware.io/v1/invoices/lex-inv-1',
            ], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-1' => \Tests\Support\FakePluginHttp::response([
                'id' => 'lex-inv-1',
                'voucherNumber' => 'RE-2030-007',
                'voucherStatus' => 'open',
            ], 200),
        ]);

        // Stellen überträgt, wenn Lexoffice den Kreis vergibt (MVP-1166).
        $this->postAsAdmin('invoices.issue', [], $invoice)->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('RE-2030-007', $invoice->number);
        // B1 (Vollscan 2026-08-23): auch der Lexoffice-Push friert Partei-Snapshot
        // und Steuerkontext ein — vorher setzte er nur den Status.
        $this->assertNotNull($invoice->party_snapshot);
        $this->assertIsArray($invoice->tax_context);
        $this->assertNotNull($invoice->due_on);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\Services\LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_id' => $invoice->id,
            'external_id' => 'lex-inv-1',
        ]);
    }

    public function test_local_numbering_issues_without_lexoffice(): void {
        $this->pluginSecret('lexoffice', ['enabled' => true, 'api_key' => 'test-key']);
        $invoice = $this->draftWithItem('R2030-0009');
        $fake = \Tests\Support\FakePluginHttp::fake();

        $this->postAsAdmin('invoices.issue', [], $invoice)->assertRedirect(route('invoices.show', $invoice));

        $fake->assertNothingSent();
        $this->assertSame(['R2030-0009', InvoiceStatus::Issued], [$invoice->refresh()->number, $invoice->status]);
    }

    public function test_draft_number_stays_while_the_program_cannot_take_the_invoice(): void {
        // orgaMAX legt per API keine Gutschrift an — Stellen bleibt gesperrt.
        $this->pluginSecret('orgamax', ['enabled' => true, 'api_key' => 'test-key']);
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::CreditNote, ['source' => 'orgamax']);
        $invoice = $this->draftWithItem(NumberSequenceService::DRAFT_PREFIX . 'LEX00002');
        $invoice->update(['type' => Invoice::TYPE_CREDIT_NOTE]);

        $this->actingAs($this->admin)->from(route('invoices.show', $invoice))
            ->post(route('invoices.issue', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', __('numbering.issue.draft_number', ['program' => app(\App\Plugins\OrgaMax\OrgaMaxPlugin::class)->name()]));

        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
    }

    public function test_draft_number_gets_a_local_number_once_workdiary_numbers_again(): void {
        $invoice = $this->draftWithItem(NumberSequenceService::DRAFT_PREFIX . 'LEX00003');

        $this->postAsAdmin('invoices.issue', [], $invoice)->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertFalse(NumberSequenceService::isDraft($invoice->number));
        $this->assertStringStartsWith('R', $invoice->number);
    }

    public function test_cancelling_a_transferred_invoice_sends_a_linked_lexoffice_correction(): void {
        $this->pluginSecret('lexoffice', ['enabled' => true, 'api_key' => 'test-key']);
        foreach ([NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation] as $scope) {
            app(NumberSequenceService::class)->setFormat($this->organization, $scope, ['source' => 'lexoffice']);
        }
        $original = $this->draftWithItem('RE-2030-010');
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($original, ['number' => 'RE-2030-010']);
        \App\Models\Integration\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\Services\LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_type' => $original->getMorphClass(),
            'referenceable_id' => $original->id,
            'external_id' => 'lex-inv-9',
        ]);

        $this->actingAs($this->admin)->post(route('invoices.cancel', $original))->assertRedirect();
        $cancellation = Invoice::query()->where('parent_invoice_id', $original->id)->firstOrFail();
        $this->assertTrue(NumberSequenceService::isDraft($cancellation->number));
        $this->assertSame(__('numbering.flash.transfer_pending', ['document' => $cancellation->documentLabel(), 'program' => 'Lexoffice']), session('status'));

        $fake = \Tests\Support\FakePluginHttp::fake([
            'https://api.lexware.io/v1/credit-notes?precedingSalesVoucherId=lex-inv-9&finalize=true' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-cn-1'], 201),
            'https://api.lexware.io/v1/credit-notes/lex-cn-1' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-cn-1', 'voucherNumber' => 'RK-2030-001'], 200),
        ]);

        $this->postAsAdmin('invoices.issue', [], $cancellation)->assertRedirect(route('invoices.show', $cancellation));

        $cancellation->refresh();
        $this->assertSame(InvoiceStatus::Issued, $cancellation->status);
        $this->assertSame('RK-2030-001', $cancellation->number);
        $fake->assertSent(function (\Psr\Http\Message\RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && is_array($body)
                && ! isset($body['dueDate'])
                && $body['title'] === __('Stornorechnung')
                && $body['lineItems'][0]['quantity'] === 1;
        });
        $reference = \App\Models\Integration\ExternalReference::query()->where('referenceable_id', $cancellation->id)->firstOrFail();
        $this->assertSame(['lex-cn-1', 'credit-notes'], [$reference->external_id, $reference->payload['_resource'] ?? null]);
    }

    public function test_correction_without_transferred_original_goes_to_lexoffice_without_link(): void {
        $this->pluginSecret('lexoffice', ['enabled' => true, 'api_key' => 'test-key']);
        app(NumberSequenceService::class)->setFormat($this->organization, NumberScope::CreditNote, ['source' => 'lexoffice']);
        $original = $this->draftWithItem('R2030-0042');
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($original);
        $credit = $this->draftWithItem(NumberSequenceService::DRAFT_PREFIX . 'GUT00001');
        $credit->update(['type' => Invoice::TYPE_CREDIT_NOTE, 'parent_invoice_id' => $original->id]);

        $fake = \Tests\Support\FakePluginHttp::fake([
            'https://api.lexware.io/v1/credit-notes?finalize=true' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-cn-2'], 201),
            'https://api.lexware.io/v1/credit-notes/lex-cn-2' => \Tests\Support\FakePluginHttp::response(['id' => 'lex-cn-2', 'voucherNumber' => 'RK-2030-002'], 200),
        ]);

        $this->postAsAdmin('invoices.issue', [], $credit->refresh())->assertRedirect();

        $this->assertSame('RK-2030-002', $credit->refresh()->number);
        $fake->assertSentCount(2);
    }

    private function draftWithItem(string $number): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'description' => 'Beratung',
            'quantity' => '1.00',
            'unit' => 'h',
            'unit_price' => '95.00',
            'position' => 1,
        ]);

        return $invoice;
    }

    public function test_publish_to_lexoffice_requires_draft_status(): void {
        $this->pluginSecret('lexoffice', ['enabled' => true, 'api_key' => 'test-key']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'ISSUED-0001',
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        // Bereits ausgestellte Rechnungen werden vom issue-Gate geblockt → 403,
        // die Lexoffice-Übertragung kommt also gar nicht erst zur Ausführung.
        $this->postAsAdmin('invoices.issue', [], $invoice)
            ->assertForbidden();

        $this->assertDatabaseMissing('external_references', [
            'referenceable_id' => $invoice->id,
            'external_type' => \App\Plugins\Lexoffice\Services\LexofficeInvoiceService::EXT_TYPE_INVOICE,
        ]);
    }

    public function test_pdf_redirects_to_plugin_when_invoice_is_linked(): void {
        // Core-PDF-Route delegiert per Redirect an die Plugin-eigene Route,
        // sobald eine external_reference vom Typ 'invoice' existiert.
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'LINKED-0001',
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\Integration\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\Services\LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_type' => $invoice->getMorphClass(),
            'referenceable_id' => $invoice->id,
            'external_id' => 'lex-inv-9',
            'synced_at' => now(),
        ]);

        $this->getAsAdmin('invoices.pdf', $invoice)
            ->assertRedirect(route('invoices.lexoffice.pdf', $invoice));
    }

    public function test_plugin_pdf_route_streams_lexoffice_pdf(): void {
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'LINKED-0002',
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        \App\Models\Integration\ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => \App\Plugins\Lexoffice\LexofficePlugin::ID,
            'external_type' => \App\Plugins\Lexoffice\Services\LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_type' => $invoice->getMorphClass(),
            'referenceable_id' => $invoice->id,
            'external_id' => 'lex-inv-9',
            'synced_at' => now(),
        ]);

        // Befund B7 (Phase 145): ein Abruf über `/file` statt `/document` + `/files/{id}`.
        $fake = \Tests\Support\FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/lex-inv-9/file' => \Tests\Support\FakePluginHttp::response('%PDF-1.4 fake', 200),
        ]);

        $response = $this->actingAs($this->admin)->get(route('invoices.lexoffice.pdf', $invoice));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $fake->assertSentCount(1);
    }

    /** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function getAsAdmin(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->get(route($routeName, $parameters));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postAsAdmin(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->post(route($routeName, $parameters), $payload);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Storno-Workflow
    // ────────────────────────────────────────────────────────────────────────

    public function test_cancel_draft_invoice(): void {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);

        $this->postAsAdmin('invoices.cancel', ['reason' => 'Kunde widerruft'], $invoice)->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame($this->admin->id, $invoice->cancelled_by);
        $this->assertSame('Kunde widerruft', $invoice->cancel_reason);
    }

    public function test_cancel_issued_invoice(): void {
        $invoice = $this->makeInvoice(InvoiceStatus::Issued, [
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
        ]);

        $this->postAsAdmin('invoices.cancel', [], $invoice)->assertRedirect();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()?->status);
    }

    public function test_cancel_paid_invoice_is_forbidden(): void {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid, [
            'issued_on' => now()->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);

        $this->postAsAdmin('invoices.cancel', [], $invoice)->assertForbidden();
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_credit_note_only_for_paid(): void {
        $draft = $this->makeInvoice(InvoiceStatus::Draft);
        $this->postAsAdmin('invoices.credit-note', [], $draft)->assertForbidden();
    }

    public function test_credit_note_creates_negative_invoice(): void {
        $original = $this->makeInvoice(InvoiceStatus::Paid, [
            'issued_on' => now()->subDays(5)->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);
        $original->items()->create([
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'h',
            'unit_price' => '90.00',
            'position' => 1,
        ]);
        $original->refresh();
        $original->recalculate();
        $original->save();

        $this->postAsAdmin('invoices.credit-note', [], $original)->assertRedirect();

        $credit = Invoice::query()->where('parent_invoice_id', $original->id)->firstOrFail();
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $credit->type);
        $this->assertStringStartsWith('G', $credit->number);
        $this->assertSame(InvoiceStatus::Draft, $credit->status);
        $this->assertSame(1, $credit->items()->count());
        /** @var \App\Models\Invoicing\InvoiceItem $item */
        $item = $credit->items()->first();
        $this->assertSame('-2.000', $item->quantity);
        $this->assertSame('-180.00', $credit->subtotal?->getAmount());
        $this->assertSame('-214.20', $credit->total?->getAmount());
    }

    public function test_credit_note_cannot_be_created_twice(): void {
        $original = $this->makeInvoice(InvoiceStatus::Paid, [
            'issued_on' => now()->subDays(5)->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);
        $this->postAsAdmin('invoices.credit-note', [], $original)->assertRedirect();
        $this->postAsAdmin('invoices.credit-note', [], $original)->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Mailversand
    // ────────────────────────────────────────────────────────────────────────

    public function test_send_invoice_queues_mail_and_updates_status(): void {
        Mail::fake();
        $template = $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);

        $this->postAsAdmin('invoices.send', [
            'template_id' => $template->id,
            'to' => ['kunde@example.test'],
            'cc' => ['cc@example.test'],
            'custom_text' => 'Vielen Dank.',
            'bcc_sender' => '1',
        ], $invoice)->assertRedirect();

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $m) use ($invoice): bool {
            return $m->invoice->is($invoice)
                && $m->hasTo('kunde@example.test')
                && $m->hasCc('cc@example.test')
                && str_contains($m->renderedSubject, $invoice->number);
        });

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(1, $invoice->sent_count);
        $this->assertNotNull($invoice->issued_on);
        $this->assertNotNull($invoice->due_on);
    }

    /**
     * Vollaudit 2026-07 (M26): Der Zustellnachweis bleibt nicht auf „queued"
     * stehen — beim tatsächlichen Versand (Sync-Queue + array-Mailer) schreibt
     * der MessageSent-Listener sent + Message-ID + Dateihash.
     */
    public function test_send_invoice_records_delivery_proof(): void {
        $template = $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);

        $this->postAsAdmin('invoices.send', [
            'template_id' => $template->id,
            'to' => ['kunde@example.test'],
        ], $invoice)->assertRedirect();

        $dispatch = \App\Models\Document\DocumentDispatch::query()
            ->where('invoice_id', $invoice->id)
            ->where('channel', \App\Models\Document\DocumentDispatch::CHANNEL_EMAIL)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(DocumentDispatchStatus::Sent, $dispatch->status, 'MessageSent-Listener schreibt queued→sent.');
        // Der array-Test-Mailer liefert keine Message-ID; belastbar sind
        // Status, Sendezeitpunkt und der PDF-Hash (aus den versendeten Bytes).
        $this->assertArrayHasKey('message_id', (array) $dispatch->meta);
        $this->assertNotNull($dispatch->meta['sent_at'] ?? null);
        $this->assertNotNull($dispatch->sha256, 'PDF-Hash wird beim Versand festgehalten.');
    }

    public function test_send_invoice_validates_emails(): void {
        $template = $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(InvoiceStatus::Issued);

        $this->postAsAdmin('invoices.send', [
            'template_id' => $template->id,
            'to' => ['nicht-eine-email'],
        ], $invoice)->assertSessionHasErrors(['to.0']);
    }

    public function test_send_form_renders(): void {
        $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);
        $this->getAsAdmin('invoices.send.form', $invoice)->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function makeInvoice(InvoiceStatus|string $status, array $overrides = []): Invoice {
        $invoice = Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-' . str_pad((string) ++self::$invoiceNo, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ], $overrides));
        // Feature 160: Entwürfe ohne Position sind nicht mehr ausstellbar — die Position deckt die Summen (100/19/119).
        if ($status === InvoiceStatus::Draft) {
            $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'amount' => '100.00', 'position' => 1]);
        }

        return $invoice;
    }

    private function makeDefaultTemplate(): InvoiceMailTemplate {
        return InvoiceMailTemplate::create([
            'organization_id' => null,
            'name' => 'Test',
            'is_default' => true,
            'subject' => '{{document_label}} {{invoice_number}}',
            'body_html' => '<p>Hallo {{customer_name}}, Betrag: {{total}} {{currency}}. {{custom_text}}</p>',
            'body_text' => 'Hallo {{customer_name}}, Betrag: {{total}} {{currency}}. {{custom_text}}',
        ]);
    }
}
