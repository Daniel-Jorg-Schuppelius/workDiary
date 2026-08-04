<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesWorklistTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Offene Zeiten (MVP-460): Buchhaltungs-Arbeitsliste unabgerechneter Zeiten —
 * Sicht-Gate timeEntry.viewAny, Filter, Summen (H:MM + dezimal), CSV,
 * zeitraum-lose Liste (nur explizite from/to-Filter schränken ein),
 * Altbestand-Abschluss (Stichtag).
 */
class OpenTimesWorklistTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    private function openEntry(array $attributes = []): TimeEntry {
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        return TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $worker->id,
            'billable' => true,
            'exported' => false,
            'minutes' => 90,
            'date' => now()->subDays(3)->toDateString(),
        ], $attributes));
    }

    public function test_accountant_sees_other_users_open_times(): void {
        $entry = $this->openEntry(['description' => 'Serverwartung Fremdeintrag']);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Serverwartung Fremdeintrag')
            ->assertSee($entry->user?->name);
    }

    public function test_plain_user_gets_403(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->get(route('finance.open-times.index'))
            ->assertForbidden();
    }

    public function test_exported_entries_are_hidden(): void {
        $this->openEntry(['description' => 'Offener Eintrag']);
        $this->openEntry(['description' => 'Bereits abgerechnet', 'exported' => true]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Offener Eintrag')
            ->assertDontSee('Bereits abgerechnet');
    }

    public function test_billable_filter_no_shows_only_non_billable(): void {
        $this->openEntry(['description' => 'Abrechenbarer Eintrag']);
        $this->openEntry(['description' => 'Kulanz-Eintrag', 'billable' => false]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index', ['billable' => 'no']))
            ->assertOk()
            ->assertSee('Kulanz-Eintrag')
            ->assertDontSee('Abrechenbarer Eintrag');
    }

    public function test_customer_filter_narrows_result(): void {
        $this->openEntry(['description' => 'Eintrag Kunde A']);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $this->openEntry(['description' => 'Eintrag Kunde B', 'project_id' => $otherProject->id]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index', ['customer' => $this->customer->sqid]))
            ->assertOk()
            ->assertSee('Eintrag Kunde A')
            ->assertDontSee('Eintrag Kunde B');
    }

    public function test_totals_show_clock_and_decimal_duration(): void {
        $this->openEntry(['minutes' => 90]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            // 90 min → "1:30 h (1,50 h)" (x-duration, Modus both).
            ->assertSee('1:30');
    }

    public function test_entries_without_project_stay_visible(): void {
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        TimeEntry::factory()->administration()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'billable' => true,
            'exported' => false,
            'description' => 'Projektloser Verwaltungseintrag',
            'date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Projektloser Verwaltungseintrag');
    }

    public function test_csv_export_contains_both_duration_formats(): void {
        $this->openEntry(['minutes' => 90, 'description' => 'CSV-Eintrag']);

        $response = $this->actingAs($this->accountant)
            ->get(route('finance.open-times.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('CSV-Eintrag', $content);
        $this->assertStringContainsString('1:30 h', $content);
        $this->assertStringContainsString('1,50', $content);
    }

    public function test_csv_export_forbidden_for_plain_user(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->get(route('finance.open-times.export'))
            ->assertForbidden();
    }

    public function test_late_entry_gets_kpi_and_badge(): void {
        // Rechnung mit Leistungsdatum NACH dem offenen Eintrag → Nachzügler.
        $invoice = \App\Models\Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-0001',
            'status' => \App\Models\Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->accountant->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Leistung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
            'service_date' => now()->subDays(2)->toDateString(),
        ]);
        $this->openEntry(['date' => now()->subDays(10)->toDateString()]);

        $response = $this->actingAs($this->accountant)->get(route('finance.open-times.index'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('lateCount'));
        $response->assertSee(__('finance.open_times.badge.late'));
    }

    public function test_invoiced_diary_with_open_times_is_flagged(): void {
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $diary = \App\Models\DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'customer_id' => $this->customer->id,
            'status' => \App\Enums\Diary\Status::Invoiced,
            'title' => 'Serverumzug Abgeschlossen',
            'invoiced_at' => now()->subDay(),
        ]);
        $this->openEntry(['diary_entry_id' => $diary->id]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee(__('finance.open_times.mismatch.heading'))
            ->assertSee('Serverumzug Abgeschlossen');
    }

    // ── Zeitraum: Liste ignoriert die Header-Zeitauswahl ────────────────────

    public function test_list_ignores_header_date_range(): void {
        // Header eng auf die letzten 10 Tage gestellt — Altbestand muss trotzdem
        // in der Liste stehen: offene Posten fallen nie lautlos aus dem Fenster.
        $this->openEntry(['description' => 'Eintrag im Fenster', 'date' => now()->subDays(5)->toDateString()]);
        $this->openEntry(['description' => 'Altbestand-Eintrag', 'date' => now()->subDays(100)->toDateString()]);

        $response = $this->actingAs($this->accountant)
            ->withSession($this->dateRangeSession(now()->subDays(10)->toDateString(), now()->toDateString()))
            ->get(route('finance.open-times.index'));

        $response->assertOk()
            ->assertSee('Eintrag im Fenster')
            ->assertSee('Altbestand-Eintrag');
        // Warn-KPI „älter als 45 Tage" zählt weiterhin.
        $this->assertSame(1, $response->viewData('staleCount'));
    }

    public function test_explicit_period_filter_narrows_list(): void {
        $this->openEntry(['description' => 'Eintrag im Fenster', 'date' => now()->subDays(5)->toDateString()]);
        $this->openEntry(['description' => 'Altbestand-Eintrag', 'date' => now()->subDays(100)->toDateString()]);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index', ['from' => now()->subDays(10)->toDateString()]))
            ->assertOk()
            ->assertSee('Eintrag im Fenster')
            ->assertDontSee('Altbestand-Eintrag');
    }

    // ── Altbestand-Abschluss: bis Stichtag als abgerechnet markieren ────────

    public function test_mark_billed_marks_billable_entries_up_to_cutoff(): void {
        $before = $this->openEntry(['date' => now()->subDays(60)->toDateString()]);
        $onCutoff = $this->openEntry(['date' => now()->subDays(30)->toDateString()]);
        $after = $this->openEntry(['date' => now()->subDays(10)->toDateString()]);
        $nonBillable = $this->openEntry(['date' => now()->subDays(60)->toDateString(), 'billable' => false]);

        $this->actingAs($this->accountant)
            ->post(route('finance.open-times.mark-billed'), ['cutoff' => now()->subDays(30)->toDateString()])
            ->assertRedirect(route('finance.open-times.index'))
            ->assertSessionHas('success');

        $this->assertTrue($before->refresh()->exported);
        $this->assertTrue($onCutoff->refresh()->exported, 'Stichtag ist einschließlich');
        $this->assertFalse($after->refresh()->exported);
        $this->assertFalse($nonBillable->refresh()->exported, 'nicht abrechenbare bleiben ohne Opt-in offen');
    }

    public function test_mark_billed_includes_non_billable_on_request(): void {
        $nonBillable = $this->openEntry(['date' => now()->subDays(60)->toDateString(), 'billable' => false]);

        $this->actingAs($this->accountant)
            ->post(route('finance.open-times.mark-billed'), [
                'cutoff' => now()->subDays(30)->toDateString(),
                'include_non_billable' => '1',
            ])
            ->assertRedirect(route('finance.open-times.index'));

        $this->assertTrue($nonBillable->refresh()->exported);
    }

    public function test_mark_billed_scopes_to_selected_customer(): void {
        $entryA = $this->openEntry(['date' => now()->subDays(60)->toDateString()]);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $entryB = $this->openEntry(['date' => now()->subDays(60)->toDateString(), 'project_id' => $otherProject->id]);

        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $projectless = TimeEntry::factory()->administration()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(60)->toDateString(),
        ]);

        $this->actingAs($this->accountant)
            ->post(route('finance.open-times.mark-billed'), [
                'cutoff' => now()->subDays(30)->toDateString(),
                'customer' => $this->customer->sqid,
            ])
            ->assertRedirect(route('finance.open-times.index'));

        $this->assertTrue($entryA->refresh()->exported);
        $this->assertFalse($entryB->refresh()->exported);
        $this->assertFalse($projectless->refresh()->exported, 'projektlose Einträge gehören zu keinem Kunden');
    }

    public function test_mark_billed_ignores_other_organizations(): void {
        $otherOrg = \App\Models\Organization::factory()->create();
        $otherWorker = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        $foreign = TimeEntry::factory()->administration()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $otherWorker->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(60)->toDateString(),
        ]);

        $this->actingAs($this->accountant)
            ->post(route('finance.open-times.mark-billed'), ['cutoff' => now()->toDateString()])
            ->assertRedirect(route('finance.open-times.index'));

        $this->assertFalse($foreign->refresh()->exported);
    }

    public function test_mark_billed_requires_cutoff(): void {
        $this->actingAs($this->accountant)
            ->from(route('finance.open-times.index'))
            ->post(route('finance.open-times.mark-billed'), [])
            ->assertSessionHasErrors('cutoff');
    }

    public function test_mark_billed_forbidden_for_plain_user(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->get(route('finance.open-times.mark-billed-dialog'))
            ->assertForbidden();

        $this->actingAs($plain)
            ->post(route('finance.open-times.mark-billed'), ['cutoff' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_mark_billed_dialog_renders_for_accountant(): void {
        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.mark-billed-dialog'))
            ->assertOk()
            ->assertSee(__('finance.open_times.mark_billed.title'));
    }

    /**
     * Kunde mit laufendem Leistungssaldo (Feature 098) + ein Zeiteintrag darauf.
     *
     * @param  'account'|'retainer'|'invoice'  $mode
     */
    private function ledgerCustomerEntry(string $mode, string $description): TimeEntry {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        \App\Models\Billing\CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'mode' => $mode,
            'active' => true,
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);

        return $this->openEntry(['project_id' => $project->id, 'description' => $description]);
    }

    public function test_account_and_retainer_customers_are_hidden_but_counted(): void {
        $this->openEntry(['description' => 'Normale Fakturierung']);
        $this->ledgerCustomerEntry('account', 'Kundenkonto-Zeit');
        $this->ledgerCustomerEntry('retainer', 'Pauschal-Zeit');

        $response = $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Normale Fakturierung')
            ->assertDontSee('Kundenkonto-Zeit')
            ->assertDontSee('Pauschal-Zeit');

        // Kontrollfunktion: die ausgeblendeten Zeiten werden gezählt gemeldet.
        $this->assertSame(2, $response->viewData('ledgerManagedCount'));
    }

    public function test_monthly_invoice_mode_stays_visible(): void {
        // Modus „monatliche Rechnung" läuft über die normale Pipeline und
        // gehört daher weiterhin in die Arbeitsliste.
        $this->ledgerCustomerEntry('invoice', 'Monatsrechnungs-Zeit');

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Monatsrechnungs-Zeit');
    }

    public function test_inactive_agreement_does_not_hide_entries(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        \App\Models\Billing\CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'mode' => 'retainer',
            'active' => false,
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $this->openEntry(['project_id' => $project->id, 'description' => 'Alte Pauschale beendet']);

        $this->actingAs($this->accountant)
            ->get(route('finance.open-times.index'))
            ->assertOk()
            ->assertSee('Alte Pauschale beendet');
    }

    public function test_mark_billed_skips_ledger_managed_customers(): void {
        // Die Massenaktion darf nie mehr treffen als die Liste zeigt — sonst
        // reißt sie Konto-Zeiten aus dem Monatsabschluss.
        $normal = $this->openEntry(['date' => now()->subDays(60)->toDateString()]);
        $ledger = $this->ledgerCustomerEntry('retainer', 'Pauschal-Altbestand');
        $ledger->update(['date' => now()->subDays(60)->toDateString()]);

        $this->actingAs($this->accountant)
            ->post(route('finance.open-times.mark-billed'), ['cutoff' => now()->subDays(30)->toDateString()])
            ->assertRedirect(route('finance.open-times.index'));

        $this->assertTrue($normal->refresh()->exported);
        $this->assertFalse($ledger->refresh()->exported);
    }
}
