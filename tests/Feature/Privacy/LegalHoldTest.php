<?php

/*
 * Filename     : LegalHoldTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Exceptions\Privacy\LegalHoldException;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\{LegalHold, RetentionProposal};
use App\Services\Privacy\{DataProtectionPermissions, UserAnonymizationService};
use App\Services\Retention\{LegalHoldService, RetentionScanService};
use App\Services\Stammdaten\CustomerMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Legal Hold (MVP-801, Feature 130 — entschieden im Vollscan 2026-09-15): Ein
 * Sperrvermerk an Person oder Kunde hält Löschkonzept, Anonymisierung und
 * Aufräumläufe an, bis er mit Begründung aufgehoben wird.
 */
final class LegalHoldTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        app()->instance('currentOrganization', $this->organization);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_officer_places_and_releases_a_hold_with_reasons(): void {
        $officer = $this->officer();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Streit GmbH']);

        $this->actingAs($officer)->post(route('dataprotection.legal-holds.store'), [
            'kind' => 'customer',
            'customer_id' => $customer->sqid,
            'reference' => 'LG Köln 12 O 34/26',
            'reason' => 'Klage auf Schadensersatz, Unterlagen sichern.',
        ])->assertRedirect(route('dataprotection.legal-holds.index'));

        $hold = LegalHold::query()->sole();
        $this->assertTrue($hold->isActive());
        $this->assertSame('Klage auf Schadensersatz, Unterlagen sichern.', $hold->reason);
        // Die Begründung liegt verschlüsselt in der Datenbank.
        $this->assertStringNotContainsString('Schadensersatz', (string) DB::table('legal_holds')->value('reason'));
        $this->assertSame(1, $customer->auditLogs()->where('event', 'legal_hold.placed')->count());

        $this->actingAs($officer)->get(route('dataprotection.legal-holds.index'))
            ->assertOk()
            ->assertSee('Streit GmbH')
            ->assertSee('LG Köln 12 O 34/26');

        $this->actingAs($officer)->post(route('dataprotection.legal-holds.release', $hold), ['release_reason' => 'Verfahren rechtskräftig beendet.'])
            ->assertRedirect(route('dataprotection.legal-holds.index'));
        $hold->refresh();
        $this->assertFalse($hold->isActive());
        $this->assertSame($officer->id, $hold->released_by);
        $this->assertSame(1, $customer->auditLogs()->where('event', 'legal_hold.released')->count());
    }

    public function test_reason_is_required_and_foreign_subjects_are_rejected(): void {
        $officer = $this->officer();
        $foreign = Customer::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $own = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($officer)->post(route('dataprotection.legal-holds.store'), ['kind' => 'customer', 'customer_id' => $own->sqid, 'reason' => 'kurz'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($officer)->post(route('dataprotection.legal-holds.store'), ['kind' => 'customer', 'customer_id' => $foreign->id, 'reason' => 'Fremder Mandant darf nicht gesperrt werden.'])
            ->assertSessionHasErrors('subject');

        $this->assertSame(0, LegalHold::query()->withoutGlobalScopes()->count());
    }

    public function test_without_data_protection_rights_the_page_is_forbidden(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($member)->get(route('dataprotection.legal-holds.index'))->assertForbidden();
    }

    public function test_retention_scan_skips_held_people_and_purge_is_blocked_when_hold_comes_later(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $held = $this->formerEmployee();
        $later = $this->formerEmployee();
        $this->legalHolds()->place($held, 'Arbeitsgerichtsverfahren anhängig.', null, $admin);

        $result = app(RetentionScanService::class)->scan($this->organization);

        $proposals = RetentionProposal::query()->where('area', 'employee_records')->get();
        $this->assertSame([$later->id], $proposals->map(fn ($p) => (int) $p->subject_id)->all());
        $this->assertGreaterThanOrEqual(1, $result['exempt']);

        // Vorschlag bestätigt, dann kommt der Vermerk: Die Löschung muss trotzdem halten.
        $proposal = $proposals->first();
        app(RetentionScanService::class)->approve($proposal, $admin);
        $this->legalHolds()->place($later, 'Auskunftsersuchen mit Klageandrohung.', 'AZ-7', $admin);

        $this->expectException(LegalHoldException::class);
        try {
            app(RetentionScanService::class)->purge($proposal->fresh(), $admin);
        } finally {
            $this->assertNull($later->fresh()?->anonymized_at);
            $this->assertSame(RetentionProposal::STATUS_APPROVED, $proposal->fresh()?->status);
        }
    }

    public function test_anonymization_and_hard_deletes_are_blocked(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $former = $this->formerEmployee();
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        foreach ([$former, $member, $customer] as $holdable) {
            $this->legalHolds()->place($holdable, 'Beweissicherung für laufendes Verfahren.', null, $admin);
        }

        try {
            app(UserAnonymizationService::class)->anonymize($former, $admin);
            $this->fail('Anonymisierung trotz Legal Hold.');
        } catch (LegalHoldException) {
            $this->assertNull($former->fresh()?->anonymized_at);
        }

        $this->actingAs($admin)->delete(route('org.members.destroy', $member))->assertSessionHas('error');
        $this->assertNotNull(User::query()->find($member->id));

        $this->actingAs($admin)->delete(route('customers.destroy', $customer))->assertSessionHas('error');
        $this->assertNotNull(Customer::query()->find($customer->id));

        // Derselbe Schutz über die API.
        Sanctum::actingAs($admin, ['*']);
        $this->deleteJson(route('api.customers.destroy', $customer))->assertStatus(423);
        $this->assertNotNull(Customer::query()->find($customer->id));
    }

    public function test_hold_follows_the_customer_into_a_merge(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $source = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $target = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $hold = $this->legalHolds()->place($source, 'Verfahren gegen den Altkunden.', null, $admin);

        app(CustomerMergeService::class)->merge($source, $target);

        $hold->refresh();
        $this->assertSame($target->id, $hold->holdable_id);
        $this->assertNotNull($this->legalHolds()->activeHoldFor($target->fresh()));
    }

    public function test_location_purge_keeps_points_of_held_people(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $held = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $free = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        foreach ([$held, $free] as $user) {
            DB::table('location_points')->insert([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'recorded_at' => now()->subDays(400),
                'lat' => 'x',
                'lng' => 'x',
                'processed_at' => now()->subDays(399),
            ]);
        }
        $this->legalHolds()->place($held, 'Streit über Arbeitszeiten, Standortnachweis nötig.', null, $admin);

        $this->artisan('location:purge-points', ['--days' => 90])->assertSuccessful();

        $this->assertSame([$held->id], DB::table('location_points')->pluck('user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_records_of_held_customers_count_as_held(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->legalHolds()->place($customer, 'Gewährleistungsstreit, Belege sichern.', null, $admin);

        $record = new \App\Models\Sales\Lead(['customer_id' => $customer->id]);

        $this->assertNotNull($this->legalHolds()->activeHoldFor($record));
        $this->assertNull($this->legalHolds()->activeHoldFor(new \App\Models\Sales\Lead(['customer_id' => null])));
    }

    private function officer(): User {
        DataProtectionPermissions::seedOrganization($this->organization);
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    private function formerEmployee(): User {
        return User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ]);
    }

    private function legalHolds(): LegalHoldService {
        return app(LegalHoldService::class);
    }
}
