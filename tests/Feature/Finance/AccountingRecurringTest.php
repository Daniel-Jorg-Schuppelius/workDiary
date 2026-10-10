<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingRecurringTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, ProfitDetermination, RecurringInterval, RecurringRunStatus, RecurringTemplateKind, RecurringTemplateStatus};
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, RecurringAccountingService};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Wiederkehrende Belegerwartungen und Buchungsvorlagen (Feature 125, MVP-675).
 *
 * Abnahme: Kein Lauf erzeugt automatisch einen Eingangsbeleg oder eine
 * Festbuchung; je Vorlage und Periode entsteht höchstens ein aktiver Vorgang.
 */
class AccountingRecurringTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $chart = app(ChartOfAccountsService::class);
        $this->accounts['expense'] = $chart->create($this->org, ['number' => '6310', 'name' => 'Miete', 'type' => AccountType::Expense]);
        $this->accounts['bank'] = $chart->create($this->org, ['number' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'is_bank' => true]);
    }

    private function service(): RecurringAccountingService {
        return app(RecurringAccountingService::class);
    }

    private function template(RecurringTemplateKind $kind, array $attributes = []): AccountingRecurringTemplate {
        $lines = $kind->createsDraft() ? [
            ['accounting_account_id' => $this->accounts['expense']->id, 'debit' => '1000.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->accounts['bank']->id, 'debit' => '0.00', 'credit' => '1000.00'],
        ] : null;

        $template = AccountingRecurringTemplate::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'kind' => $kind,
            'name' => $kind === RecurringTemplateKind::DocumentExpectation ? 'Telefonrechnung' : 'Mietabgrenzung',
            'interval' => RecurringInterval::Monthly,
            'due_day' => 5,
            'starts_on' => $this->startsOn->toDateString(),
            'status' => RecurringTemplateStatus::Active,
            'version' => 1,
            'expected_amount' => '1000.00',
            'currency' => CurrencyCode::Euro,
            'template_lines' => $lines,
            'responsible_user_id' => $this->admin->id,
        ], $attributes));

        $template->update(['next_due_on' => $this->service()->firstDue($template)->toDateString()]);

        return $template->refresh();
    }

    /** Die zentrale Zusage: eine Erwartung erzeugt keinen Beleg. */
    public function test_a_document_expectation_creates_no_document_and_no_entry(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);

        $run = $this->service()->runOnce($template, $this->admin);

        $this->assertInstanceOf(AccountingRecurringRun::class, $run);
        $this->assertSame(RecurringRunStatus::Expected, $run->status);
        $this->assertNull($run->accounting_entry_id);
        $this->assertSame(0, IncomingEInvoice::query()->count());
        $this->assertSame(0, AccountingEntry::query()->count());
    }

    /** Und eine Buchungsvorlage erzeugt nur einen Entwurf, nie eine Festbuchung. */
    public function test_a_posting_template_creates_a_draft_only(): void {
        $template = $this->template(RecurringTemplateKind::PostingTemplate);

        $run = $this->service()->runOnce($template, $this->admin);

        $this->assertSame(RecurringRunStatus::DraftCreated, $run?->status);
        $entry = AccountingEntry::query()->sole();
        $this->assertSame(AccountingEntryStatus::Draft, $entry->status);
        $this->assertNull($entry->journal_no);
        $this->assertNull($entry->posted_at);
    }

    public function test_a_second_run_of_the_same_period_creates_no_duplicate(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);

        $first = $this->service()->runOnce($template, $this->admin);
        // Zeiger zurücksetzen, als wäre der Lauf erneut fällig.
        $template->update(['next_due_on' => $first?->due_on->toDateString()]);
        $second = $this->service()->runOnce($template->refresh(), $this->admin);

        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(1, AccountingRecurringRun::query()->count());
    }

    public function test_the_due_pointer_advances_by_the_interval(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $firstDue = CarbonImmutable::parse($template->next_due_on);

        $this->service()->runOnce($template, $this->admin);

        $this->assertSame(
            $firstDue->addMonthNoOverflow()->toDateString(),
            $template->refresh()->next_due_on?->toDateString(),
        );
    }

    public function test_run_due_processes_only_due_templates(): void {
        $this->template(RecurringTemplateKind::DocumentExpectation);
        $this->template(RecurringTemplateKind::DocumentExpectation, [
            'name' => 'Versicherung',
            'starts_on' => $this->startsOn->addYear()->toDateString(),
        ]);

        $result = $this->service()->runDue($this->org, $this->startsOn->addDays(10), $this->admin);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, AccountingRecurringRun::query()->count());
    }

    public function test_the_original_document_fulfils_the_expectation(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $run = $this->service()->runOnce($template, $this->admin);
        $this->assertNotNull($run);

        $document = \App\Models\Document\Document::factory()->create(['organization_id' => $this->org->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', 'telefon-01'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'TK-2026-01',
            'seller_name' => 'Telefon AG',
            'issue_date' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'amount_gross' => '1000.00',
        ]);

        $fulfilled = $this->service()->fulfill($run, $incoming);

        $this->assertSame(RecurringRunStatus::Fulfilled, $fulfilled->status);
        $this->assertSame(MorphMap::alias(IncomingEInvoice::class), $fulfilled->fulfilled_by_type);
        $this->assertSame($incoming->id, $fulfilled->fulfilled_by_id);
    }

    public function test_a_closed_run_cannot_be_fulfilled_twice(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $run = $this->service()->runOnce($template, $this->admin);
        $document = \App\Models\Document\Document::factory()->create(['organization_id' => $this->org->id]);
        $this->service()->fulfill($run, $document);

        $this->expectException(ValidationException::class);
        $this->service()->fulfill($run->refresh(), $document);
    }

    /** Ein blockierter Lauf bleibt sichtbar, statt still zu verschwinden. */
    public function test_a_template_without_lines_blocks_visibly(): void {
        $template = $this->template(RecurringTemplateKind::PostingTemplate, ['template_lines' => null]);

        $run = $this->service()->runOnce($template, $this->admin);

        $this->assertSame(RecurringRunStatus::Blocked, $run?->status);
        $this->assertNotEmpty($run?->blocked_reason);
        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_pause_stops_the_run_and_resume_moves_the_pointer_forward(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);

        $this->service()->pause($template);
        $result = $this->service()->runDue($this->org, $this->startsOn->addMonths(3), $this->admin);
        $this->assertSame(0, $result['created']);

        $resumed = $this->service()->resume($template->refresh());
        // Kein Nachholen der Pausenzeit: der Zeiger steht in der Zukunft.
        $this->assertTrue($resumed->next_due_on?->greaterThanOrEqualTo(now()->startOfDay()) ?? false);
    }

    public function test_ending_a_template_clears_the_pointer(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);

        $ended = $this->service()->end($template);

        $this->assertSame(RecurringTemplateStatus::Ended, $ended->status);
        $this->assertNull($ended->next_due_on);
    }

    public function test_preview_lists_the_next_due_dates(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);

        $preview = $this->service()->preview($template, 3);

        $this->assertCount(3, $preview);
        $this->assertSame('2026-01-05', $preview[0]);
        $this->assertSame('2026-02-05', $preview[1]);
    }

    public function test_overdue_runs_are_notified_once(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $this->service()->runOnce($template, $this->admin);

        $first = $this->service()->notifyOverdue($this->org, $this->startsOn->addMonths(2));
        $second = $this->service()->notifyOverdue($this->org, $this->startsOn->addMonths(2));

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertNotNull(AccountingRecurringRun::query()->sole()->notified_at);
    }

    public function test_the_command_creates_expectations_for_local_organizations(): void {
        $this->template(RecurringTemplateKind::DocumentExpectation);

        $this->artisan('accounting:run-recurring', ['--date' => $this->startsOn->addDays(10)->toDateString()])
            ->assertExitCode(0);

        $this->assertSame(1, AccountingRecurringRun::query()->count());
    }

    public function test_recurring_pages_require_permissions(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $member = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($member)->get(route('finance.accounting.recurring.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.edit', $template))->assertOk();
    }

    public function test_creating_a_template_over_http_sets_the_first_due_date(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), [
            'kind' => RecurringTemplateKind::DocumentExpectation->value,
            'name' => 'Bürostrom',
            'interval' => RecurringInterval::Quarterly->value,
            'due_day' => 10,
            'starts_on' => $this->startsOn->toDateString(),
            'expected_amount' => '250.00',
        ])->assertRedirect();

        $template = AccountingRecurringTemplate::query()->where('name', 'Bürostrom')->sole();
        $this->assertSame('2026-01-10', $template->next_due_on?->toDateString());
        $this->assertSame(RecurringInterval::Quarterly, $template->interval);
    }

    /** Eine Buchungsvorlage ohne Konten wird sofort abgewiesen, nicht nachts — als Feldfehler im Dialog. */
    public function test_an_incomplete_posting_template_is_rejected_at_save_time(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), [
            'kind' => RecurringTemplateKind::PostingTemplate->value,
            'name' => 'Unvollständig',
            'interval' => RecurringInterval::Monthly->value,
            'due_day' => 1,
            'starts_on' => $this->startsOn->toDateString(),
        ])->assertSessionHasErrors(['debit_account', 'credit_account', 'expected_amount']);

        $this->assertSame(0, AccountingRecurringTemplate::query()->count());
    }

    /** MVP-1102: Der Bearbeiten-Dialog belegt Soll und Haben aus der Vorlage vor. */
    public function test_the_edit_dialog_preselects_the_template_accounts(): void {
        $template = $this->template(RecurringTemplateKind::PostingTemplate);

        $html = $this->actingAs($this->admin)
            ->get(route('finance.accounting.recurring.edit', $template))
            ->assertOk()
            ->getContent();

        $this->assertSame($this->accounts['expense']->sqid, $this->selectedOption((string) $html, 'debit_account'));
        $this->assertSame($this->accounts['bank']->sqid, $this->selectedOption((string) $html, 'credit_account'));
        $this->assertSame($this->admin->sqid, $this->selectedOption((string) $html, 'responsible_user_id'));
    }

    /** Unverändert gespeichert entsteht eine neue Fassung mit denselben Zeilen — kein Abweisen. */
    public function test_saving_an_unchanged_posting_template_keeps_its_lines(): void {
        $template = $this->template(RecurringTemplateKind::PostingTemplate);

        $this->actingAs($this->admin)->put(route('finance.accounting.recurring.update', $template), [
            'kind' => $template->kind->value,
            'name' => $template->name,
            'interval' => $template->interval->value,
            'due_day' => $template->due_day,
            'starts_on' => $template->starts_on->toDateString(),
            'expected_amount' => '1000.00',
            'debit_account' => $this->accounts['expense']->sqid,
            'credit_account' => $this->accounts['bank']->sqid,
            'responsible_user_id' => $this->admin->sqid,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $template->refresh();
        $this->assertSame(2, $template->version);
        $this->assertSame($this->accounts['expense']->id, $template->template_lines[0]['accounting_account_id'] ?? null);
        $this->assertSame($this->accounts['bank']->id, $template->template_lines[1]['accounting_account_id'] ?? null);
    }

    /** MVP-1102: „Verantwortlich" ist setzbar — nur mit Mitgliedern der eigenen Organisation. */
    public function test_the_responsible_person_can_be_set_within_the_organization(): void {
        $colleague = User::factory()->create(['organization_id' => $this->org->id]);
        $stranger = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $payload = [
            'kind' => RecurringTemplateKind::DocumentExpectation->value,
            'name' => 'Leasing',
            'interval' => RecurringInterval::Monthly->value,
            'due_day' => 3,
            'starts_on' => $this->startsOn->toDateString(),
        ];

        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), $payload + ['responsible_user_id' => $stranger->sqid])
            ->assertSessionHasErrors('responsible_user_id');
        $this->assertSame(0, AccountingRecurringTemplate::query()->count());

        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.store'), $payload + ['responsible_user_id' => $colleague->sqid])
            ->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, AccountingRecurringTemplate::query()->sole()->responsible_user_id);
    }

    /** MVP-1102: Eine Belegerwartung wird erfüllt, indem jemand die eingegangene Rechnung zuordnet. */
    public function test_an_expectation_is_fulfilled_by_assigning_the_incoming_invoice(): void {
        $template = $this->template(RecurringTemplateKind::DocumentExpectation);
        $run = $this->service()->runOnce($template, $this->admin);
        $this->assertNotNull($run);
        $incoming = $this->incomingInvoice($this->org, 'TK-2026-01');
        $foreign = $this->incomingInvoice(Organization::factory()->create(), 'FREMD-1');

        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.runs.fulfill-form', $run))
            ->assertOk()
            ->assertSee('TK-2026-01')
            ->assertDontSee('FREMD-1');

        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.runs.fulfill', $run), ['incoming_einvoice_id' => $foreign->sqid])
            ->assertSessionHasErrors('incoming_einvoice_id');
        $this->assertSame(RecurringRunStatus::Expected, $run->refresh()->status);

        $this->actingAs($this->admin)->post(route('finance.accounting.recurring.runs.fulfill', $run), ['incoming_einvoice_id' => $incoming->sqid])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(RecurringRunStatus::Fulfilled, $run->status);
        $this->assertTrue(MorphMap::is($run->fulfilled_by_type, IncomingEInvoice::class));
        $this->assertSame($incoming->id, $run->fulfilled_by_id);

        // Eine Rechnung erfüllt höchstens einen Vorgang.
        $next = $this->service()->runOnce($template->refresh(), $this->admin);
        $this->assertNotNull($next);
        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.runs.fulfill-form', $next))
            ->assertOk()
            ->assertDontSee('TK-2026-01');
    }

    /** Den Vorgang einer Buchungsvorlage erfüllt man nicht von Hand — ihn schließt die Festschreibung. */
    public function test_a_posting_template_run_cannot_be_fulfilled_by_hand(): void {
        $run = $this->service()->runOnce($this->template(RecurringTemplateKind::PostingTemplate), $this->admin);
        $this->assertNotNull($run);

        $this->actingAs($this->admin)->get(route('finance.accounting.recurring.runs.fulfill-form', $run))->assertNotFound();
    }

    /** MVP-1102: Wird der Entwurf festgeschrieben, ist der Vorgang erledigt und nicht mehr überfällig. */
    public function test_posting_the_draft_completes_the_run(): void {
        $run = $this->service()->runOnce($this->template(RecurringTemplateKind::PostingTemplate), $this->admin);
        $this->assertNotNull($run);
        $this->assertSame(RecurringRunStatus::DraftCreated, $run->status);

        $entry = AccountingEntry::query()->sole();
        app(\App\Services\Accounting\JournalService::class)->post($entry, $this->admin);

        $run->refresh();
        $this->assertSame(RecurringRunStatus::Fulfilled, $run->status);
        $this->assertTrue(MorphMap::is($run->fulfilled_by_type, AccountingEntry::class));
        $this->assertSame($entry->id, $run->fulfilled_by_id);
        $this->assertSame(0, $this->service()->notifyOverdue($this->org, $this->startsOn->addYear()));
    }

    private function incomingInvoice(Organization $organization, string $number): IncomingEInvoice {
        $document = \App\Models\Document\Document::factory()->create([
            'organization_id' => $organization->id,
            'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
        ]);

        return IncomingEInvoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', $number),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => $number,
            'seller_name' => 'Telefon AG',
            'issue_date' => $this->startsOn->addDays(5)->toDateString(),
            'currency' => 'EUR',
            'amount_gross' => '1000.00',
        ]);
    }

    /** Wert der ausgewählten Option eines Auswahlfelds im gerenderten Dialog. */
    private function selectedOption(string $html, string $name): ?string {
        if (preg_match('/<select[^>]*name="' . preg_quote($name, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $select) !== 1) {
            return null;
        }

        return preg_match('/<option value="([^"]*)"\s+selected/', $select[1], $option) === 1 ? $option[1] : null;
    }
}
