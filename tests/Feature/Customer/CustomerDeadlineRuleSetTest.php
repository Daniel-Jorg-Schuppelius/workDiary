<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineRuleSetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Customer\CustomerDeadlineStatus;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Customer\{Customer, CustomerDeadline, CustomerService};
use App\Models\Platform\User;
use App\Services\Customer\Deadlines\{CustomerDeadlineGenerator, CustomerDeadlineProfileService, CustomerDeadlineRuleSets, CustomerDeadlineService};
use App\Services\Customer\Deadlines\RuleSets\{HomeCareDeadlineRuleSet, TaxAdvisoryDeadlineRuleSet};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Regelsätze und Jahreslauf der Kundenfristen (Feature 197, MVP-1630–1632,
 * MVP-1673, MVP-1676): Abnahmekriterien 1, 2, 3, 5 und 8 über den ganzen Weg
 * Fristmerkmale → Jahreslauf → `customer_deadlines`. Rechtsquellen der Termine:
 * `tests/Fixtures/filing-deadlines/*` und die Kopfkommentare der Regelsätze.
 * Kunden und Personen sind erfunden.
 */
final class CustomerDeadlineRuleSetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->admin = $this->orgAdmin();
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY, HomeCareDeadlineRuleSet::KEY]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $name = 'Bäckerei Probe'): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
    }

    /** @param array<string, mixed> $traits */
    private function profile(Customer $customer, string $ruleSet, array $traits, string $from = '2026-01-01', ?User $responsible = null): void {
        $set = app(CustomerDeadlineRuleSets::class)->get($this->organization, $ruleSet);
        $this->assertNotNull($set);
        app(CustomerDeadlineProfileService::class)->save($customer, $set, $traits, CarbonImmutable::parse($from), $responsible, $this->admin);
    }

    private function service(Customer $customer, string $slug, ?string $productGroup, PeriodKind $kind, string $from = '2026-01-01'): void {
        $type = EntryType::query()->where('organization_id', $this->organization->id)->where('slug', $slug)->first()
            ?? EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => $slug, 'label' => ucfirst($slug)]);
        CustomerService::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'entry_type_id' => $type->id,
            'product_group_code' => $productGroup,
            'period_kind' => $kind,
            'valid_from' => $from,
        ]);
    }

    private function due(Customer $customer, string $kind, string $period): ?string {
        return CustomerDeadline::query()
            ->where('customer_id', $customer->id)
            ->where('kind', $kind)
            ->where('period_key', $period)
            ->first()?->due_on->toDateString();
    }

    /** @param list<int> $years */
    private function sync(Customer $customer, string $ruleSet, array $years): void {
        $set = app(CustomerDeadlineRuleSets::class)->get($this->organization, $ruleSet);
        $this->assertNotNull($set);
        app(CustomerDeadlineGenerator::class)->syncCustomer($customer, $set, $years);
    }

    /** Abnahme 1: monatliche USt mit Dauerfristverlängerung — Januar 2027 am 10.03.2027, Dezember 2026 am 10.02.2027. */
    public function test_monthly_vat_with_extension(): void {
        $customer = $this->customer();
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'monthly', 'vat_extension' => true, 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin']);
        $this->sync($customer, TaxAdvisoryDeadlineRuleSet::KEY, [2026, 2027]);

        $this->assertSame('2027-03-10', $this->due($customer, 'vat_advance', '2027-01'));
        $this->assertSame('2027-02-10', $this->due($customer, 'vat_advance', '2026-12'));
        // Sondervorauszahlung nur bei Monatsabgabe mit Verlängerung (§ 48 UStDV).
        $this->assertSame('2026-02-10', $this->due($customer, 'vat_special_prepayment', '2026'));
        $this->assertNull($this->due($customer, 'wage_tax', '2026-01'));
    }

    /** Abnahme 2: Jahreserklärung 2025 eines beratenen Kunden am 01.03.2027 — je Leistung im Leistungsumfang. */
    public function test_annual_returns_follow_the_service_scope(): void {
        $customer = $this->customer();
        $this->service($customer, 'steuererklaerung', 'einkommensteuer', PeriodKind::Year, '2025-01-01');
        $this->service($customer, 'steuererklaerung', 'umsatzsteuer', PeriodKind::Year, '2025-01-01');
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'none', 'wage_tax_interval' => 'none', 'tax_advised' => true, 'tax_office_state' => 'Germany\\Berlin'], '2025-01-01');
        $this->sync($customer, TaxAdvisoryDeadlineRuleSet::KEY, [2025]);

        $this->assertSame('2027-03-01', $this->due($customer, 'annual_return:einkommensteuer', '2025'));
        $this->assertSame('2027-03-01', $this->due($customer, 'annual_return:umsatzsteuer', '2025'));
        $this->assertNull($this->due($customer, 'annual_return:koerperschaftsteuer', '2025'));
    }

    /** Abnahme 3: Feiertag im Bundesland des Finanzamts verschiebt, Feiertag nur am Sitz der Organisation nicht. */
    public function test_the_holiday_at_the_tax_office_counts_not_the_one_at_the_organisation(): void {
        // Reformationstag 31.10.2025 (Freitag): Feiertag in Niedersachsen, nicht in Bayern.
        $this->organization->forceFill(['settings' => ['holidays' => ['provider' => 'Germany\\LowerSaxony']]])->save();
        $traits = ['vat_interval' => 'none', 'wage_tax_interval' => 'none', 'tax_advised' => true, 'agriculture' => true, 'fiscal_year_start' => 7];

        $lowerSaxony = $this->customer('Hof Niedersachsen');
        $this->service($lowerSaxony, 'steuererklaerung', 'einkommensteuer', PeriodKind::Year, '2023-01-01');
        $this->profile($lowerSaxony, TaxAdvisoryDeadlineRuleSet::KEY, $traits + ['tax_office_state' => 'Germany\\LowerSaxony'], '2025-01-01');
        $bavaria = $this->customer('Hof Bayern');
        $this->service($bavaria, 'steuererklaerung', 'einkommensteuer', PeriodKind::Year, '2023-01-01');
        $this->profile($bavaria, TaxAdvisoryDeadlineRuleSet::KEY, $traits + ['tax_office_state' => 'Germany\\Bavaria'], '2025-01-01');
        $this->sync($lowerSaxony, TaxAdvisoryDeadlineRuleSet::KEY, [2023]);
        $this->sync($bavaria, TaxAdvisoryDeadlineRuleSet::KEY, [2023]);

        // Übergang Art. 97 § 36 Abs. 3 Nr. 2 EGAO: 2023 → 31.10.2025.
        $this->assertSame('2025-11-03', $this->due($lowerSaxony, 'annual_return:einkommensteuer', '2023'));
        $this->assertSame('2025-10-31', $this->due($bavaria, 'annual_return:einkommensteuer', '2023'));
    }

    /** Ohne Bundesland rechnet der Regelsatz mit der Region der Organisation und markiert die Frist. */
    public function test_a_missing_state_falls_back_to_the_organisation_and_is_marked(): void {
        $customer = $this->customer();
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'quarterly', 'wage_tax_interval' => 'none']);
        $this->sync($customer, TaxAdvisoryDeadlineRuleSet::KEY, [2026]);

        $deadline = CustomerDeadline::query()->where('customer_id', $customer->id)->where('period_key', '2026-Q1')->sole();
        $this->assertTrue($deadline->is_region_assumed);
        $this->assertSame('2026-04-10', $deadline->due_on->toDateString());
    }

    /** Abnahme 8: Lohnkunde, Januar 2026 — Nachweis 26.01., Fälligkeit 28.01.; zweite Einzugsstelle mit Feiertag zieht vor. */
    public function test_payroll_customers_get_social_security_dates_from_the_earliest_state(): void {
        $customer = $this->customer();
        $this->service($customer, 'lohn', null, PeriodKind::Month);
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'none', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin', 'contribution_states' => ['Germany\\Berlin']]);
        $this->sync($customer, TaxAdvisoryDeadlineRuleSet::KEY, [2026, 2027]);

        $this->assertSame('2026-01-26', $this->due($customer, 'contribution_statement', '2026-01'));
        $this->assertSame('2026-01-28', $this->due($customer, 'contribution_due', '2026-01'));
        $this->assertSame('2027-05-27', $this->due($customer, 'contribution_due', '2027-05'));
        // Lohnsteuer-Anmeldung Dezember 2026: 10.01.2027 (Sonntag) → 11.01., keine Verlängerung.
        $this->assertSame('2027-01-11', $this->due($customer, 'wage_tax', '2026-12'));

        // Zweite Einzugsstelle in Bayern: Fronleichnam 27.05.2027 zieht Fälligkeit und Nachweis vor.
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'none', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin', 'contribution_states' => ['Germany\\Berlin', 'Germany\\Bavaria']], '2026-03-02');
        $this->sync($customer, TaxAdvisoryDeadlineRuleSet::KEY, [2026, 2027]);
        $this->assertSame('2027-05-26', $this->due($customer, 'contribution_due', '2027-05'));
        $this->assertSame('2027-05-24', $this->due($customer, 'contribution_statement', '2027-05'));
    }

    /** Abnahme 5: Pflegegrad 4 mit Pflegegeld je Halbjahr, „vierteljährlich gewünscht“ je Quartal, ohne Pflegegeld keine Pflichtfrist. */
    public function test_counselling_visits_follow_care_grade_and_benefit(): void {
        $grade = Classification::factory()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::Pflegegrad->value, 'code' => 'pg4', 'label' => 'Pflegegrad 4']);
        $halfYear = $this->customer('Klientin Halbjahr');
        $halfYear->classifications()->attach($grade->id);
        $this->profile($halfYear, HomeCareDeadlineRuleSet::KEY, ['benefit' => 'care_allowance']);
        $quarter = $this->customer('Klient Quartal');
        $quarter->classifications()->attach($grade->id);
        $this->profile($quarter, HomeCareDeadlineRuleSet::KEY, ['benefit' => 'care_allowance', 'quarterly_requested' => true]);
        $inKind = $this->customer('Klientin Sachleistung');
        $inKind->classifications()->attach($grade->id);
        $this->profile($inKind, HomeCareDeadlineRuleSet::KEY, ['benefit' => 'combination']);

        $kinds = static fn (Customer $c): array => CustomerDeadline::query()->where('customer_id', $c->id)->orderBy('period_key')->pluck('period_key')->map(static fn ($p): string => (string) $p)->all();
        $this->assertSame(['2026-H1', '2026-H2', '2027-H1', '2027-H2'], $kinds($halfYear));
        $this->assertContains('2026-Q4', $kinds($quarter));
        $this->assertNotContains('2026-H1', $kinds($quarter));
        $this->assertSame([], $kinds($inKind));
        // 31.12.2026 ist ein Donnerstag und gesetzlich kein Feiertag.
        $this->assertSame('2026-06-30', $this->due($halfYear, 'counselling_visit', '2026-H1'));
        $this->assertSame('2026-12-31', $this->due($halfYear, 'counselling_visit', '2026-H2'));
    }

    /** Eine Merkmalsänderung rechnet nur offene Fristen neu; Erledigtes und Vorangefordertes bleiben. */
    public function test_a_trait_change_recalculates_only_open_deadlines(): void {
        $customer = $this->customer();
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'monthly', 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin']);
        $service = app(CustomerDeadlineService::class);
        $april = CustomerDeadline::query()->where('customer_id', $customer->id)->where('period_key', '2026-04')->sole();
        $march = CustomerDeadline::query()->where('customer_id', $customer->id)->where('period_key', '2026-03')->sole();
        $service->complete($march, $this->admin);
        $may = CustomerDeadline::query()->where('customer_id', $customer->id)->where('period_key', '2026-05')->sole();
        $service->requestAdvance($may, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-06-01'), $this->admin);

        // Dauerfristverlängerung ab sofort.
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'monthly', 'vat_extension' => true, 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin'], '2026-03-02');

        $this->assertSame('2026-06-10', $april->refresh()->due_on->toDateString());
        $this->assertSame(CustomerDeadlineStatus::Completed, $march->refresh()->status);
        $this->assertSame('2026-04-10', $march->due_on->toDateString());
        $this->assertSame('2026-06-01', $may->refresh()->due_on->toDateString(), 'von Hand gesetzt bleibt stehen');
        $this->assertSame('2026-07-10', $may->original_due_on->toDateString(), 'die Berechnung wird mitgeführt');
        $this->assertSame(['deadline_created', 'deadline_recalculated'], $april->journal()->pluck('event')->all());
    }

    /** Fristen vor dem Stichtag der ersten Fassung entstehen nicht, noch offene danach schon; der Lauf ist idempotent. */
    public function test_the_first_version_owns_only_what_is_due_after_it_starts(): void {
        $customer = $this->customer();
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'monthly', 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin'], '2026-03-02');

        // Januar fällig 10.02. (davor) — fehlt; Februar fällig 10.03. — gehört dazu.
        $this->assertNull($this->due($customer, 'vat_advance', '2026-01'));
        $this->assertSame('2026-03-10', $this->due($customer, 'vat_advance', '2026-02'));
        $count = CustomerDeadline::query()->where('customer_id', $customer->id)->count();

        $again = app(CustomerDeadlineGenerator::class)->syncOrganization($this->organization);
        $this->assertSame(0, $again['created']);
        $this->assertSame($count, CustomerDeadline::query()->where('customer_id', $customer->id)->count());
    }

    /** Wegfall: unberührte offene Fristen verschwinden, angefasste bleiben. */
    public function test_obsolete_untouched_deadlines_are_removed(): void {
        $customer = $this->customer();
        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'none', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin']);
        $noted = CustomerDeadline::query()->where('customer_id', $customer->id)->where('period_key', '2026-06')->sole();
        $noted->update(['note' => 'Unterlagen angefordert']);

        $this->profile($customer, TaxAdvisoryDeadlineRuleSet::KEY, ['vat_interval' => 'none', 'wage_tax_interval' => 'none', 'tax_office_state' => 'Germany\\Berlin'], '2026-03-02');

        $this->assertNull($this->due($customer, 'wage_tax', '2026-05'));
        $this->assertNotNull($noted->fresh());
        // Februar gehört zur alten Fassung (Periode endet vor dem Stichtag) und bleibt.
        $this->assertNotNull($this->due($customer, 'wage_tax', '2026-02'));
    }
}
