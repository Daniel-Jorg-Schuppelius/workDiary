<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyIncidentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{IncidentStatus, IncidentType};
use App\Models\{Organization, User};
use App\Models\Privacy\{Dpia, Incident, ProcessingActivity};
use App\Services\Privacy\{DataProtectionPermissions, IncidentService, PrivacyDeadlineService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP 3: Datenschutzvorfälle (Art. 33/34), Maßnahmen, DSFA. */
class PrivacyIncidentTest extends TestCase {
    use RefreshDatabase;

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

    public function test_incident_lifecycle_with_crypto_and_chain(): void {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $svc = app(IncidentService::class);

        $incident = $svc->open($org, IncidentType::UnauthorizedAccess, 'Unbefugter Zugriff auf Kundendaten', 'E-Mail-Adressen von 200 Kunden', null, $actor);
        $this->assertSame(IncidentStatus::Detected, $incident->status);
        $this->assertNotNull($incident->authority_deadline_at);
        $this->assertStringStartsWith('DSV-', $incident->incident_number);

        $svc->assess($incident, 'high', 'Zugang gesperrt, Passwörter zurückgesetzt', $actor);
        $svc->decideNotification($incident, true, true, $actor);
        $svc->markReported($incident, true, true, $actor);
        $svc->close($incident, 'Zugriffskonzept überarbeitet', $actor);

        $fresh = Incident::findOrFail($incident->id);
        $this->assertSame(IncidentStatus::Closed, $fresh->status);
        $this->assertSame('Unbefugter Zugriff auf Kundendaten', $fresh->summary_ciphertext); // entschlüsselt
        $this->assertNotNull($fresh->authority_notified_at);
        $this->assertSame(5, $fresh->events()->count()); // opened, assessed, decided, reported, closed
        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_overdue_incident_is_reminded_idempotently(): void {
        $org = Organization::factory()->create();
        $svc = app(IncidentService::class);
        $incident = $svc->open($org, IncidentType::Loss, 'Laptop verloren');
        $incident->forceFill(['authority_deadline_at' => Carbon::now()->subHour()])->save();

        $this->assertSame(1, app(PrivacyDeadlineService::class)->remindIncidents());
        $this->assertSame(0, app(PrivacyDeadlineService::class)->remindIncidents(), 'Idempotent.');
    }

    public function test_measure_tracking(): void {
        $org = Organization::factory()->create();
        $actor = User::factory()->create(['organization_id' => $org->id]);
        $svc = app(IncidentService::class);
        $incident = $svc->open($org, IncidentType::Disclosure, 'Offenlegung');

        $measure = $svc->addMeasure($incident, 'Mitarbeiter schulen', null, Carbon::now()->addWeek(), $actor);
        $this->assertSame('open', $measure->status);
        $svc->completeMeasure($measure, $actor);
        $this->assertSame('done', $measure->fresh()->status);
    }

    public function test_dpia_upsert_via_http(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $activity = ProcessingActivity::create([
            'organization_id' => $org->id, 'name' => 'Profiling', 'controller_role' => 'controller', 'status' => 'draft',
        ]);

        $this->actingAs($officer)->post(route('dataprotection.activities.dpia', $activity), [
            'necessity' => 'erforderlich', 'risks' => 'hoch', 'outcome' => 'proceed', 'residual_risk' => 'medium',
        ])->assertRedirect();

        $dpia = Dpia::where('activity_id', $activity->id)->firstOrFail();
        $this->assertSame('proceed', $dpia->outcome->value);
        $this->assertNotNull($dpia->assessed_at);
        $this->assertTrue($activity->fresh()->dsfa_required);
    }

    public function test_officer_access_and_forbidden(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $this->actingAs($officer)->get(route('dataprotection.incidents.index'))->assertOk();

        $plain = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($plain)->get(route('dataprotection.incidents.index'))->assertForbidden();
    }

    public function test_free_plan_gated(): void {
        $freeOrg = Organization::factory()->free()->create();
        $this->actingAs($this->officer($freeOrg))->get(route('dataprotection.incidents.index'))->assertStatus(423);
    }

    public function test_tenant_isolation(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);
        $foreign = app(IncidentService::class)->open(Organization::factory()->create(), IncidentType::Loss, 'Fremd');

        $this->actingAs($officer)->get(route('dataprotection.incidents.show', $foreign))->assertNotFound();
    }
}
