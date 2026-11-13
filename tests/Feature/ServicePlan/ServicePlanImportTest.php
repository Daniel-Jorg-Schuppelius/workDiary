<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\ServicePlan;

use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Enums\User\UserRole;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerDeadlineProfile, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\ServicePlan\{CustomerServicePeriodNote, CustomerServiceWaiver};
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Customer\Deadlines\RuleSets\TaxAdvisoryDeadlineRuleSet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Listen übernehmen und Kundenliste abgleichen (Feature 199, MVP-1640),
 * darunter Abnahme 5: eine unbekannte Kundennummer legt nichts an und wird
 * gemeldet. Erfundene Kunden und Nummern.
 */
final class ServicePlanImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $anna;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-05-15 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->anna = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Anna Beispiel', 'email' => 'anna@example.test']);
        EntryType::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'abschluss',
            'label' => 'Jahresabschluss',
            'requires_customer' => true,
            'requires_period' => [PeriodKind::Year->value],
        ]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function csv(string $content): UploadedFile {
        return UploadedFile::fake()->createWithContent('liste.csv', $content);
    }

    /** Abnahme 5 und Jahresliste: Leistungsumfang, Bemerkung, Auftrag der laufenden Periode. */
    public function test_year_list_imports_known_customers_and_reports_unknown_numbers(): void {
        $bakery = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bäckerei Probe', 'number' => 'K-100']);
        $before = Customer::query()->count();
        $file = $this->csv("Kundennummer;Leistung;Stand;Bemerkung;Zuständig\nK-100;Jahresabschluss;in Arbeit;Belege fehlen;Anna Beispiel\nK-999;Jahresabschluss;erledigt;\n");

        $preview = $this->actingAs($this->anna)->post(route('service-plan.import.preview'), ['file' => $file, 'year' => 2025, 'create_orders' => 1]);
        $preview->assertOk()
            ->assertSee(__('service_plan.import.issue.unknown_customer', ['number' => 'K-999']))
            ->assertViewHas('analysis', static fn (array $analysis): bool => $analysis['kind'] === 'year' && count($analysis['rows']) === 2);
        $this->assertSame(0, CustomerService::query()->count(), 'Die Vorschau übernimmt nichts.');

        $this->actingAs($this->anna)->post(route('service-plan.import.store'))
            ->assertRedirect(route('service-plan.index', ['year' => 2025]))
            ->assertSessionHas('success');

        $this->assertSame($before, Customer::query()->count(), 'Eine unbekannte Kundennummer legt keinen Kunden an.');
        $service = CustomerService::query()->sole();
        $this->assertSame((int) $bakery->id, (int) $service->customer_id);
        $this->assertSame(PeriodKind::Year, $service->period_kind);
        $this->assertSame('2025-01-01', $service->valid_from->toDateString());
        $this->assertSame('Belege fehlen', CustomerServicePeriodNote::query()->sole()->note);
        $order = DiaryEntry::query()->where('customer_id', $bakery->id)->sole();
        $this->assertSame(Status::InProgress, $order->status);
        $this->assertSame('2025', $order->period_key?->key());
        $this->assertSame((int) $this->anna->id, (int) $order->assigned_user_id);
        $this->assertTrue($order->journal()->where('event', 'order.importedFromPlan')->exists());
    }

    /** Periodenliste mit Monatsspalten und Merkmalen: Umfang ab der laufenden Periode, „entfällt“, Merkmale als Fassung. */
    public function test_period_list_with_traits_and_waivers(): void {
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
        $bookkeeping = EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'fibu', 'label' => 'Finanzbuchführung', 'requires_customer' => true]);
        $client = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Planungsbüro Probe', 'number' => '20001']);
        $file = $this->csv("Mandant;Jan;Feb;Mär;Apr;Mai;Jun;Umsatzsteuer-Voranmeldung;Lohnsteuer-Anmeldung\n20001;x;x;erledigt;erledigt;in Arbeit;entfällt;vierteljährlich;keine\n");

        $this->actingAs($this->anna)->post(route('service-plan.import.preview'), [
            'file' => $file,
            'year' => 2026,
            'service' => (new \App\Services\ServicePlan\Dto\ServiceKey((int) $bookkeeping->id))->param(),
            'create_orders' => 1,
        ])->assertOk()->assertViewHas('analysis', static fn (array $analysis): bool => $analysis['kind'] === 'periods'
            && $analysis['period_kind'] === PeriodKind::Month
            && $analysis['current']->key() === '2026-05'
            && array_keys($analysis['traits']) === ['vat_interval', 'wage_tax_interval']);

        $this->actingAs($this->anna)->post(route('service-plan.import.store'))->assertRedirect(route('service-plan.periods.index', ['year' => 2026]));

        $service = CustomerService::query()->where('customer_id', $client->id)->sole();
        $this->assertSame(PeriodKind::Month, $service->period_kind);
        $this->assertSame('2026-05-01', $service->valid_from->toDateString(), 'Umfang ab der laufenden Periode — vergangene Monate bleiben in der Liste.');
        $this->assertSame(Status::InProgress, DiaryEntry::query()->where('customer_id', $client->id)->sole()->status);
        $this->assertSame('2026-06', CustomerServiceWaiver::query()->sole()->period_key->key());
        $profile = CustomerDeadlineProfile::query()->where('customer_id', $client->id)->sole();
        $this->assertSame('quarterly', $profile->traitValues()->get('vat_interval'));
    }

    /** Excel-Listen liest derselbe Weg über den XLSX-Parser des common-toolkit (erstes Blatt, Datum als `Y-m-d`). */
    public function test_excel_list_is_read_like_csv(): void {
        $content = \App\Support\XlsxExport::toString(['Kundennummer', 'Leistung', 'Stand', 'Unterlagen da'], [['K-100', 'Jahresabschluss', 'erledigt', '2026-02-15']]);
        $path = \CommonToolkit\Helper\FileSystem\File::createTemp($content, 'leistungsplan', 'xlsx');
        try {
            $table = app(\App\Services\ServicePlan\Import\ServicePlanTableReader::class)->read($path, 'xlsx');
        } finally {
            \CommonToolkit\Helper\FileSystem\File::delete($path);
        }
        $this->assertSame(['Kundennummer', 'Leistung', 'Stand', 'Unterlagen da'], $table['header']);
        $this->assertSame(['K-100', 'Jahresabschluss', 'erledigt', '2026-02-15'], array_values($table['rows'])[0]);
    }

    public function test_ambiguous_or_unknown_service_and_bad_state_are_reported(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1']);
        EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'abschluss_zwei', 'label' => 'Jahresabschluss']);
        $file = $this->csv("Kundennummer;Leistung;Stand\nK-1;Jahresabschluss;fertig\nK-1;Lohn;irgendwas\n");

        $this->actingAs($this->anna)->post(route('service-plan.import.preview'), ['file' => $file, 'year' => 2025])
            ->assertOk()
            ->assertSee(__('service_plan.import.issue.ambiguous_service', ['value' => 'Jahresabschluss']))
            ->assertSee(__('service_plan.import.issue.unknown_service', ['value' => 'Lohn']))
            ->assertSee(__('service_plan.import.issue.unknown_state', ['value' => 'irgendwas']));
    }

    public function test_templates_download_and_permissions(): void {
        $this->actingAs($this->anna)->get(route('service-plan.import.template', 'jahresliste'))->assertOk()->assertSee('Kundennummer');
        $this->actingAs($this->anna)->get(route('service-plan.import.template', 'periodenliste'))->assertOk();
        $this->actingAs($this->anna)->get(route('service-plan.reconcile.template'))->assertOk()->assertSee('Mandantennummer');
        $this->actingAs($this->anna)->get(route('service-plan.import.create'))->assertOk();

        $worker = $this->userWithRole(UserRole::User->value);
        $this->actingAs($worker)->get(route('service-plan.import.create'))->assertForbidden();
        $this->actingAs($worker)->post(route('service-plan.import.preview'), ['file' => $this->csv("Kundennummer\nK-1\n"), 'year' => 2025])->assertForbidden();
        $this->actingAs($worker)->get(route('service-plan.reconcile.create'))->assertForbidden();
    }

    /** Kundenabgleich (Vorlage DATEV): neue, umbenannte, fehlende — übernommen wird nur Angekreuztes. */
    public function test_customer_list_reconciliation_only_applies_confirmed_proposals(): void {
        $admin = $this->orgAdmin();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bäckerei Probe', 'number' => '10001']);
        $renamed = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Altname GmbH', 'number' => '10002']);
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nur bei uns KG', 'number' => '10003']);
        $file = $this->csv("EXTF;700;16\nMandantennummer;Name;Ort\n10001;bäckerei  probe;Musterstadt\n10002;Neuname GmbH;Musterstadt\n10004;Neukunde AG;Beispielhausen\n10005;Zweiter Neukunde;Beispielhausen\n;Ohne Nummer;\n");

        $this->actingAs($admin)->post(route('service-plan.reconcile.preview'), ['file' => $file])
            ->assertOk()
            ->assertViewHas('analysis', static fn (array $analysis): bool => $analysis['counts']['new'] === 2
                && $analysis['counts']['renamed'] === 1
                && $analysis['counts']['unchanged'] === 1
                && $analysis['counts']['invalid'] === 1
                && $analysis['counts']['missing'] === 1)
            ->assertSee('Nur bei uns KG');
        $this->assertSame('Altname GmbH', $renamed->refresh()->name, 'Die Vorschau ändert nichts.');

        $this->actingAs($admin)->post(route('service-plan.reconcile.store'), ['create_numbers' => ['10004'], 'rename_numbers' => ['10002']])
            ->assertRedirect(route('service-plan.reconcile.create'));

        $this->assertSame('Neuname GmbH', $renamed->refresh()->name);
        $this->assertTrue(Customer::query()->where('number', '10004')->where('name', 'Neukunde AG')->exists());
        $this->assertFalse(Customer::query()->where('number', '10005')->exists(), 'Nicht angekreuzt — nicht angelegt.');
    }
}
