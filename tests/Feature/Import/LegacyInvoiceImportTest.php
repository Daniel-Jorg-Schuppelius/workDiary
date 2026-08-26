<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyInvoiceImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Models\Accounting\AccountingEntry;
use App\Models\{Customer, ImportRun, Invoice, User};
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Import\Specs\InvoiceSpec;
use App\Services\Invoicing\DunningService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Altrechnungen mit OP-Stand (MVP-707): Eröffnungs-OP ohne Journalzeile,
 * sichtbar für Mahnlauf/OPOS, unsichtbar für Buchungsvorschlag und DATEV.
 */
class LegacyInvoiceImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->admin = $this->orgAdmin();
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'K-1',
            'name' => 'Altkunde GmbH',
            'country' => 'DE',
        ]);
    }

    public function test_wizard_imports_legacy_invoices_as_opening_items_seen_by_dunning_without_journal_entries(): void {
        $issued = now()->subDays(60)->format('d.m.Y');
        $due = now()->subDays(30)->format('d.m.Y');
        $csv = "Rechnungsnummer;Kundennummer;Rechnungsdatum;Fälligkeit;Netto;Steuersatz;Brutto;Bezahlt\n"
            . "RE-1001;K-1;{$issued};{$due};1000,00;19;1190,00;190,00\n"
            . "RE-1002;K-1;{$issued};{$due};200,00;19;238,00;238,00\n";

        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => ImportEntity::Invoices->value,
            'file' => UploadedFile::fake()->createWithContent('alt.csv', $csv),
        ])->assertRedirect();

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->assertSame(0, $run->rows_failed);

        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();
        $run->refresh();
        $this->assertSame(ImportRunState::Succeeded, $run->state);
        $this->assertSame(2, $run->rows_created);

        $open = Invoice::query()->where('external_number', 'RE-1001')->firstOrFail();
        $paid = Invoice::query()->where('external_number', 'RE-1002')->firstOrFail();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $open->status);
        $this->assertSame(Invoice::STATUS_PAID, $paid->status);
        $this->assertSame(InvoiceSpec::NUMBER_SOURCE, $open->number_source);
        $this->assertTrue($open->isOverdue());

        // Eröffnungs-OP: Mahnlauf sieht den Rest (Brutto − Altzahlung), OPOS-Cockpit listet die Rechnung.
        $dunning = app(DunningService::class);
        $this->assertSame(1000.0, $dunning->openAmount($open)->toFloat());
        $this->assertTrue($dunning->isLocallyBilled($open));
        $this->actingAs($this->accountant)
            ->get(route('finance.dunning.index'))
            ->assertOk()
            ->assertSee('ALT-RE-1001')
            ->assertDontSee('ALT-RE-1002');

        // Keine Journalzeile — und der Buchungsvorschlag überspringt Altrechnungen.
        $this->assertSame(0, AccountingEntry::query()->where('organization_id', $this->organization->id)->count());
        $candidates = app(SalesInvoiceAdapter::class)->candidates(
            $this->organization,
            CarbonImmutable::now()->subYear(),
            CarbonImmutable::now()->addDay(),
        );
        $this->assertTrue($candidates->isEmpty(), 'Altrechnungen erzeugen keine Buchungsvorschläge');

        // Wiederholter Lauf derselben Datei: keine Dubletten.
        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => ImportEntity::Invoices->value,
            'file' => UploadedFile::fake()->createWithContent('alt.csv', $csv),
        ]);
        $second = ImportRun::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $second));
        $this->assertSame(2, Invoice::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(2, $second->fresh()?->rows_skipped);
    }

    public function test_preflight_blocks_rows_for_customers_with_external_invoicing(): void {
        Customer::query()->where('number', 'K-1')->update(['billing_mode' => \App\Enums\Finance\BillingMode::Datev->value]);
        $csv = "external_number;customer_number;issued_on;gross_amount;tax_rate\nRE-9;K-1;2024-01-01;119;19\n";

        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => ImportEntity::Invoices->value,
            'file' => UploadedFile::fake()->createWithContent('alt.csv', $csv),
        ]);

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $run->rows_failed);
        $this->assertSame('blocked', $run->errors()->firstOrFail()->code->value);
    }
}
