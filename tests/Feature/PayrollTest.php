<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PayrollTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\EmploymentType;
use App\Models\{MinimumWage, User, WorkSchedule};
use App\Services\Payroll\{MinimumWageService, PayrollClassifier};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PayrollTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function hr(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function member(array $attrs = []): User {
        return User::factory()->user()->create(array_merge(['organization_id' => $this->organization->id], $attrs));
    }

    // ── Zugriff + Org-Stammdaten ──────────────────────────────────────────────

    public function test_only_payroll_managers_can_access(): void {
        $this->actingAs($this->member())->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($this->hr())->get(route('payroll.index'))->assertOk();
    }

    public function test_org_payroll_settings_are_saved(): void {
        $this->actingAs($this->hr())
            ->put(route('payroll.settings.update'), [
                'company_number' => '12345678',
                'tax_office' => 'Finanzamt Musterstadt',
                'tax_number' => '123/456/78901',
                'vat_id' => 'DE123456789',
                'register' => 'HRB 4711, Amtsgericht Musterstadt',
            ])
            ->assertRedirect(route('payroll.index'));

        $org = $this->organization->fresh();
        // Payroll-/SV-spezifisch in settings['payroll'].
        $this->assertSame('12345678', $org->payroll('company_number'));
        $this->assertSame('Finanzamt Musterstadt', $org->payroll('tax_office'));
        // Steuer-Identifikatoren geteilt mit Branding (settings['branding']['legal']).
        $this->assertSame('123/456/78901', $org->settings['branding']['legal']['tax_number'] ?? null);
        $this->assertSame('DE123456789', $org->settings['branding']['legal']['vat_id'] ?? null);
        $this->assertSame('HRB 4711, Amtsgericht Musterstadt', $org->settings['branding']['legal']['register'] ?? null);
    }

    public function test_payroll_settings_do_not_clobber_other_branding(): void {
        // Bestehende Branding-Daten (z. B. Bankverbindung) dürfen erhalten bleiben.
        $this->organization->update(['settings' => ['branding' => ['legal' => ['iban' => 'DE00', 'tax_number' => 'OLD']]]]);

        $this->actingAs($this->hr())
            ->put(route('payroll.settings.update'), ['tax_number' => 'NEW'])
            ->assertRedirect();

        $legal = $this->organization->fresh()->settings['branding']['legal'];
        $this->assertSame('NEW', $legal['tax_number']);
        $this->assertSame('DE00', $legal['iban']); // unangetastet
    }

    // ── Mindestlohn-Historie ──────────────────────────────────────────────────

    public function test_minimum_wage_resolves_by_date(): void {
        MinimumWage::factory()->create(['organization_id' => $this->organization->id, 'valid_from' => '2024-01-01', 'hourly_amount' => '12.41']);
        MinimumWage::factory()->create(['organization_id' => $this->organization->id, 'valid_from' => '2025-01-01', 'hourly_amount' => '12.82']);

        $svc = new MinimumWageService;
        $this->assertSame(12.41, $svc->currentFor(\Carbon\CarbonImmutable::parse('2024-06-01'), $this->organization->id));
        $this->assertSame(12.82, $svc->currentFor(\Carbon\CarbonImmutable::parse('2025-06-01'), $this->organization->id));
        // Minijob-Grenze = round(12.82 × 130 / 3) = 556 €.
        $this->assertSame(556, $svc->minijobMonthlyLimit(\Carbon\CarbonImmutable::parse('2025-06-01'), $this->organization->id));
    }

    public function test_hr_can_add_minimum_wage(): void {
        $this->actingAs($this->hr())
            ->post(route('payroll.minimum-wages.store'), ['valid_from' => '2025-01-01', 'hourly_amount' => '12.82'])
            ->assertRedirect(route('payroll.index'));

        $this->assertTrue(
            MinimumWage::where('organization_id', $this->organization->id)
                ->whereDate('valid_from', '2025-01-01')
                ->where('hourly_amount', '12.82')
                ->exists()
        );
    }

    public function test_seeder_populates_country_history(): void {
        \Database\Seeders\MinimumWageSeeder::seedOrganization($this->organization);

        $count = MinimumWage::where('organization_id', $this->organization->id)->count();
        $this->assertSame(count(\Database\Seeders\MinimumWageSeeder::HISTORY['DE']), $count);

        $svc = new MinimumWageService;
        $this->assertSame(12.82, $svc->currentFor(\Carbon\CarbonImmutable::parse('2025-06-01'), $this->organization->id));
        $this->assertSame(8.50, $svc->currentFor(\Carbon\CarbonImmutable::parse('2016-01-01'), $this->organization->id));
    }

    public function test_seeder_is_country_aware(): void {
        // Land ohne hinterlegte Historie → keine Sätze.
        $this->organization->update(['settings' => ['payroll' => ['country' => 'AT']]]);
        \Database\Seeders\MinimumWageSeeder::seedOrganization($this->organization->fresh());

        $this->assertSame(0, MinimumWage::where('organization_id', $this->organization->id)->count());
    }

    public function test_hr_can_load_history_via_action(): void {
        $this->actingAs($this->hr())
            ->post(route('payroll.minimum-wages.seed'))
            ->assertRedirect(route('payroll.index'));

        $this->assertSame(
            count(\Database\Seeders\MinimumWageSeeder::HISTORY['DE']),
            MinimumWage::where('organization_id', $this->organization->id)->count(),
        );
    }

    // ── Unter-Mindestlohn: Liste + Anhebung ───────────────────────────────────

    public function test_raise_all_below_minimum(): void {
        MinimumWage::factory()->create(['organization_id' => $this->organization->id, 'valid_from' => '2025-01-01', 'hourly_amount' => '12.82']);
        $low = $this->member(['payroll_hourly_wage' => '10.00']);
        $ok = $this->member(['payroll_hourly_wage' => '15.00']);

        $this->actingAs($this->hr())
            ->post(route('payroll.raise-to-minimum'))
            ->assertRedirect(route('payroll.index'));

        $this->assertSame('12.82', $low->fresh()->payroll_hourly_wage?->getAmount());
        $this->assertSame('15.00', $ok->fresh()->payroll_hourly_wage?->getAmount());
    }

    public function test_raise_single_user(): void {
        MinimumWage::factory()->create(['organization_id' => $this->organization->id, 'valid_from' => '2025-01-01', 'hourly_amount' => '12.82']);
        $a = $this->member(['payroll_hourly_wage' => '10.00']);
        $b = $this->member(['payroll_hourly_wage' => '11.00']);

        $this->actingAs($this->hr())
            ->post(route('payroll.raise-to-minimum'), ['user' => \App\Support\Sqid::encode(User::class, $a->id)])
            ->assertRedirect();

        $this->assertSame('12.82', $a->fresh()->payroll_hourly_wage?->getAmount());
        $this->assertSame('11.00', $b->fresh()->payroll_hourly_wage?->getAmount()); // unverändert
    }

    // ── Beschäftigungsart + Plausibilität ─────────────────────────────────────

    public function test_employment_type_saved_via_member_update(): void {
        $hr = $this->hr();
        $member = $this->member();

        $this->actingAs($hr)
            ->put(route('org.members.update', $member), [
                'employment_type' => EmploymentType::Teilzeit->value,
            ])
            ->assertRedirect(route('org.members.index'));

        $this->assertSame(EmploymentType::Teilzeit, $member->fresh()->employment_type);
    }

    public function test_classifier_flags_minijob_over_limit(): void {
        MinimumWage::factory()->create(['organization_id' => $this->organization->id, 'valid_from' => '2025-01-01', 'hourly_amount' => '12.82']);
        $member = $this->member([
            'payroll_hourly_wage' => '14.00',
            'employment_type' => EmploymentType::Minijob->value,
        ]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $member->id,
            'weekly_minutes' => 600, // 10 h/Woche → ~43 h/Monat → ~607 € > 556 €
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2025-01-01',
        ]);

        $hint = (new PayrollClassifier)->mismatchHint($member->fresh());
        $this->assertNotNull($hint);
        $this->assertStringContainsString('Minijob-Grenze', $hint);
    }
}
