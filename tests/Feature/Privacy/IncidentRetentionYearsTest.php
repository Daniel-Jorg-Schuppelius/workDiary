<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentRetentionYearsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\IncidentType;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\RetentionProposal;
use App\Services\Privacy\{DataProtectionPermissions, IncidentService};
use App\Services\Retention\{RetentionRegistry, RetentionScanService};
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aufbewahrung abgeschlossener Datenschutzvorfälle je Organisation
 * (MVP-1683): Vorgabe 3 Jahre (CH 5), eigener Wert auf der Seite
 * „Aufbewahrung & Löschung“, der Löschscan rechnet mit dem Wert der
 * Organisation.
 */
final class IncidentRetentionYearsTest extends TestCase {
    use RefreshDatabase;

    private const string KEY = 'privacy.incident_retention_years';

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    public function test_default_follows_the_legal_region(): void {
        $registry = app(RetentionRegistry::class);

        $this->assertSame(3, $registry->yearsFor(Organization::factory()->create(['legal_region' => 'DE']), 'privacy_incidents'));
        $this->assertSame(5, $registry->yearsFor(Organization::factory()->create(['legal_region' => 'CH']), 'privacy_incidents'));
        $this->assertSame('privacy.incident_retention_years', $registry->yearsSettingFor('privacy_incidents'));
        $this->assertNull($registry->yearsSettingFor('privacy_requests'), 'Nur die Vorfälle sind je Organisation einstellbar.');
    }

    public function test_the_officer_sets_the_period_and_the_scan_uses_it(): void {
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        app()->instance('currentOrganization', $org);
        $officer = $this->officer($org);
        $incidents = app(IncidentService::class);
        $incident = $incidents->open($org, IncidentType::Misdelivery, 'Fehlversand eines Lohnzettels', 'Eine Person', null, $officer);
        $incidents->close($incident, 'Versandprozess angepasst', $officer);

        $this->actingAs($officer)->get(route('dataprotection.retention.index'))->assertOk()
            ->assertSee(__('privacy.retention.years_default', ['years' => 3]));
        $this->actingAs($officer)->put(route('dataprotection.retention.years'), ['area' => 'privacy_incidents', 'years' => '5'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(5, app(RetentionRegistry::class)->yearsFor($org->refresh(), 'privacy_incidents'));

        // Nach drei Jahren (Vorgabe) schlägt der Scan nichts vor — die Organisation hält fünf.
        $scanner = app(RetentionScanService::class);
        $this->travelTo(now()->addYears(3)->addDays(2));
        $scanner->scan($org->refresh());
        $this->assertSame(0, RetentionProposal::query()->where('area', 'privacy_incidents')->count());

        $this->travelTo(now()->addYears(2));
        $scanner->scan($org->refresh());
        $this->assertSame(1, RetentionProposal::query()->where('area', 'privacy_incidents')->count());
    }

    public function test_empty_value_returns_to_the_default_and_limits_hold(): void {
        $org = Organization::factory()->create(['legal_region' => 'CH']);
        app()->instance('currentOrganization', $org);
        $officer = $this->officer($org);
        app(SettingsRegistry::class)->set(self::KEY, 8, \App\Settings\SettingScope::Organization, $org);

        $this->actingAs($officer)->put(route('dataprotection.retention.years'), ['area' => 'privacy_incidents', 'years' => '11'])
            ->assertSessionHasErrors('years');
        $this->actingAs($officer)->put(route('dataprotection.retention.years'), ['area' => 'privacy_incidents', 'years' => '0'])
            ->assertSessionHasErrors('years');
        $this->assertSame(8, app(RetentionRegistry::class)->yearsFor($org->refresh(), 'privacy_incidents'));

        $this->actingAs($officer)->put(route('dataprotection.retention.years'), ['area' => 'privacy_incidents', 'years' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, app(RetentionRegistry::class)->yearsFor($org->refresh(), 'privacy_incidents'), 'Leer heißt Vorgabe des Rechtsraums.');
        $this->assertSame(\INF, SettingsRegistry::organizationValue($org, self::KEY), 'Der Override ist entfernt, nicht leer gespeichert.');
    }

    public function test_only_adjustable_areas_and_only_with_the_right(): void {
        $org = Organization::factory()->create(['legal_region' => 'DE']);
        app()->instance('currentOrganization', $org);
        $officer = $this->officer($org);

        $this->actingAs($officer)->put(route('dataprotection.retention.years'), ['area' => 'privacy_requests', 'years' => '5'])
            ->assertNotFound();

        $member = User::factory()->user()->create(['organization_id' => $org->id]);
        $this->actingAs($member)->put(route('dataprotection.retention.years'), ['area' => 'privacy_incidents', 'years' => '5'])
            ->assertForbidden();
        $this->assertSame(3, app(RetentionRegistry::class)->yearsFor($org->refresh(), 'privacy_incidents'));
    }
}
