<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DefaultHourlyRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Organization, Project, TimeEntry, User};
use App\Services\Billing\OrganizationDefaultRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Organisationsweiter Standard-Stundensatz (MVP-482): letzte Stufe der
 * Satzhierarchie, ohne Snapshot im Eintrag.
 */
class DefaultHourlyRateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = $this->orgUser(['hourly_rate' => null, 'internal_rate' => null]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => null,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'hourly_rate' => null,
        ]);
    }

    private function setDefaultRate(?float $rate, ?Organization $organization = null): void {
        $organization ??= $this->organization;
        $organization->update(['settings' => ['invoicing' => ['default_hourly_rate' => $rate]]]);
        // Der Resolver cached je Request/Job — im Test explizit verwerfen.
        app(OrganizationDefaultRateResolver::class)->flush();
    }

    private function entry(array $attributes = []): TimeEntry {
        return TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'minutes' => 120,
            'billable' => true,
        ], $attributes));
    }

    public function test_ohne_standardsatz_bleibt_der_eintrag_bei_null(): void {
        $entry = $this->entry();

        $this->assertSame(0.0, $entry->rate?->toFloat());
    }

    public function test_standardsatz_bewertet_zeiten_ohne_gepflegten_satz(): void {
        $this->setDefaultRate(90.0);

        $entry = $this->entry();

        $this->assertSame(180.0, $entry->rate?->toFloat());
        // Der Standardsatz wird bewusst NICHT eingefroren.
        $this->assertNull($entry->fresh()?->hourly_rate);
    }

    public function test_projektsatz_schlaegt_den_standardsatz(): void {
        $this->setDefaultRate(90.0);
        $this->project->update(['hourly_rate' => 120.0]);

        $entry = $this->entry();

        $this->assertSame(240.0, $entry->rate?->toFloat());
        $this->assertSame(120.0, $entry->fresh()?->hourly_rate?->toFloat());
    }

    public function test_kundensatz_schlaegt_den_standardsatz(): void {
        $this->setDefaultRate(90.0);
        $this->customer->update(['hourly_rate' => 110.0]);

        $entry = $this->entry();

        $this->assertSame(220.0, $entry->rate?->toFloat());
    }

    public function test_nicht_abrechenbare_zeiten_bleiben_bei_null(): void {
        $this->setDefaultRate(90.0);

        $entry = $this->entry(['billable' => false]);

        $this->assertSame(0.0, $entry->rate?->toFloat());
    }

    public function test_standardsatz_wirkt_nur_in_seiner_organisation(): void {
        $this->setDefaultRate(90.0);

        $other = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $other->id, 'hourly_rate' => null]);
        $otherProject = Project::factory()->create([
            'organization_id' => $other->id,
            'customer_id' => $otherCustomer->id,
            'hourly_rate' => null,
        ]);
        $otherUser = User::factory()->user()->create(['organization_id' => $other->id, 'hourly_rate' => null]);

        $entry = TimeEntry::factory()->create([
            'organization_id' => $other->id,
            'project_id' => $otherProject->id,
            'user_id' => $otherUser->id,
            'minutes' => 120,
            'billable' => true,
        ]);

        $this->assertSame(0.0, $entry->rate?->toFloat());
    }

    public function test_geaenderter_standardsatz_bewertet_offene_zeiten_neu(): void {
        $this->setDefaultRate(90.0);
        $entry = $this->entry();
        $this->assertSame(180.0, $entry->rate?->toFloat());

        $this->setDefaultRate(100.0);
        $entry->applyRateSnapshot();

        $this->assertSame(200.0, $entry->rate?->toFloat());
    }
}
