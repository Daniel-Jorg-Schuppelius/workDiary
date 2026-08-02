<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReapplyOpenRatesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Billing\OrganizationDefaultRateResolver;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachbewertung offener Zeiten nach Einführung/Änderung des Org-Standardsatzes
 * (MVP-482).
 */
class ReapplyOpenRatesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = $this->orgUser(['hourly_rate' => null, 'internal_rate' => null]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'hourly_rate' => null]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'hourly_rate' => null,
        ]);
    }

    /** Eintrag entsteht ohne Standardsatz (rate 0,00 €), der Satz kommt danach. */
    private function openEntry(array $attributes = []): TimeEntry {
        return TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 120,
            'billable' => true,
            'exported' => false,
        ], $attributes));
    }

    private function setDefaultRate(float $rate): void {
        $this->organization->update(['settings' => ['invoicing' => ['default_hourly_rate' => $rate]]]);
        app(OrganizationDefaultRateResolver::class)->flush();
    }

    public function test_dry_run_schreibt_nichts(): void {
        $entry = $this->openEntry();
        $this->setDefaultRate(90.0);

        $this->artisan('billing:reapply-open-rates')->assertSuccessful();

        $this->assertSame(0.0, $entry->fresh()?->rate?->toFloat());
    }

    public function test_apply_bewertet_offene_zeiten_neu(): void {
        $entry = $this->openEntry();
        $this->setDefaultRate(90.0);

        $this->artisan('billing:reapply-open-rates', ['--apply' => true])->assertSuccessful();

        $this->assertSame(180.0, $entry->fresh()?->rate?->toFloat());
    }

    public function test_exportierte_zeiten_bleiben_unberuehrt(): void {
        $entry = $this->openEntry(['exported' => true]);
        $this->setDefaultRate(90.0);

        $this->artisan('billing:reapply-open-rates', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0.0, $entry->fresh()?->rate?->toFloat());
    }

    public function test_eigener_stundensatz_bleibt_unberuehrt(): void {
        $entry = $this->openEntry();
        $entry->forceFill(['hourly_rate' => Money::ofFloat(50.0, \CommonToolkit\Enums\CurrencyCode::Euro)])->save();
        $expected = $entry->fresh()?->rate?->toFloat();
        $this->setDefaultRate(90.0);

        $this->artisan('billing:reapply-open-rates', ['--apply' => true])->assertSuccessful();

        $this->assertSame($expected, $entry->fresh()?->rate?->toFloat());
        $this->assertSame(50.0, $entry->fresh()?->hourly_rate?->toFloat());
    }

    public function test_kundenfilter_grenzt_ein(): void {
        $entry = $this->openEntry();

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'hourly_rate' => null]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'hourly_rate' => null,
        ]);
        $otherEntry = $this->openEntry(['project_id' => $otherProject->id]);

        $this->setDefaultRate(90.0);

        $this->artisan('billing:reapply-open-rates', ['--customer' => (string) $otherCustomer->sqid, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame(0.0, $entry->fresh()?->rate?->toFloat());
        $this->assertSame(180.0, $otherEntry->fresh()?->rate?->toFloat());
    }
}
