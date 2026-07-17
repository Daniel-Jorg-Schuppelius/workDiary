<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSecurityIncidentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\SecurityIncidentStatus;
use App\Models\Isms\{IsmsControl, IsmsRisk, IsmsSecurityIncident};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsmsSecurityIncidentTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_incident_with_running_number(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.incidents.index'))
            ->post(route('isms.incidents.store'), [
                'title' => 'Phishing-Welle gegen Buchhaltung',
                'description' => 'Mehrere gefälschte Rechnungs-Mails.',
                'category' => 'phishing',
                'severity' => 'high',
                'detected_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_security_incidents', [
            'title' => 'Phishing-Welle gegen Buchhaltung',
            'organization_id' => $admin->organization_id,
            'incident_no' => 1,
            'status' => SecurityIncidentStatus::Reported->value,
        ]);

        $this->actingAs($admin)
            ->post(route('isms.incidents.store'), [
                'title' => 'Serverraum-Klimaausfall',
                'category' => 'serviceOutage',
                'severity' => 'medium',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_security_incidents', ['title' => 'Serverraum-Klimaausfall', 'incident_no' => 2]);
    }

    public function test_status_machine_rejects_invalid_transition(): void {
        $admin = User::factory()->admin()->create();
        $incident = $this->makeIncident($admin, ['status' => SecurityIncidentStatus::Reported->value]);

        // reported → recovered ist kein erlaubter Direktsprung.
        $this->actingAs($admin)
            ->from(route('isms.incidents.index'))
            ->post(route('isms.incidents.transition', $incident), ['status' => SecurityIncidentStatus::Recovered->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(SecurityIncidentStatus::Reported, $incident->refresh()->status);
    }

    public function test_close_requires_root_cause_and_lessons_learned(): void {
        $admin = User::factory()->admin()->create();
        $incident = $this->makeIncident($admin, [
            'status' => SecurityIncidentStatus::Recovered->value,
        ]);

        // Ohne Ursache/Lessons Learned schlägt der Abschluss fehl.
        $this->actingAs($admin)
            ->from(route('isms.incidents.index'))
            ->post(route('isms.incidents.transition', $incident), ['status' => SecurityIncidentStatus::Closed->value])
            ->assertSessionHasErrors('status');
        $this->assertSame(SecurityIncidentStatus::Recovered, $incident->refresh()->status);

        // Mit gepflegten Pflichtfeldern gelingt der Abschluss und setzt closed_at.
        $incident->update(['root_cause' => 'Fehlende MFA.', 'lessons_learned' => 'MFA überall aktiviert.']);
        $this->actingAs($admin)
            ->post(route('isms.incidents.transition', $incident), ['status' => SecurityIncidentStatus::Closed->value])
            ->assertRedirect();

        $incident->refresh();
        $this->assertSame(SecurityIncidentStatus::Closed, $incident->status);
        $this->assertNotNull($incident->closed_at);
    }

    public function test_contained_transition_sets_contained_at(): void {
        $admin = User::factory()->admin()->create();
        $incident = $this->makeIncident($admin, ['status' => SecurityIncidentStatus::Triage->value]);

        $this->actingAs($admin)
            ->post(route('isms.incidents.transition', $incident), ['status' => SecurityIncidentStatus::Contained->value])
            ->assertRedirect();

        $this->assertNotNull($incident->refresh()->contained_at);
    }

    public function test_incident_links_risks_and_controls(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $risk = IsmsRisk::factory()->create(['organization_id' => $admin->organization_id]);
        $control = IsmsControl::factory()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->post(route('isms.incidents.store'), [
                'title' => 'Unbefugter Zugriff',
                'category' => 'unauthorizedAccess',
                'severity' => 'high',
                'risk_ids' => [$risk->sqid],
                'control_ids' => [$control->sqid],
            ])
            ->assertRedirect();

        $incident = IsmsSecurityIncident::query()->where('title', 'Unbefugter Zugriff')->firstOrFail();
        $this->assertTrue($incident->risks->contains($risk));
        $this->assertTrue($incident->controls->contains($control));
    }

    public function test_personal_data_flag_and_privacy_ref_are_stored_without_merging(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('isms.incidents.store'), [
                'title' => 'Datenabfluss Kundenliste',
                'category' => 'dataLoss',
                'severity' => 'critical',
                'personal_data_affected' => '1',
                'privacy_incident_ref' => 'DS-2026-007',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_security_incidents', [
            'title' => 'Datenabfluss Kundenliste',
            'personal_data_affected' => true,
            'privacy_incident_ref' => 'DS-2026-007',
        ]);
    }

    public function test_regular_user_cannot_access_or_manage(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.incidents.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.incidents.store'), ['title' => 'X', 'category' => 'other', 'severity' => 'low'])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.incidents.index'))->assertOk();
        $this->actingAs($gf)
            ->post(route('isms.incidents.store'), ['title' => 'X', 'category' => 'other', 'severity' => 'low'])
            ->assertForbidden();
    }

    public function test_cross_organization_incident_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-si-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreign = $this->makeIncident($otherAdmin);

        $this->actingAs($admin)
            ->put(route('isms.incidents.update', $foreign), [
                'title' => 'Hijack',
                'category' => 'other',
                'severity' => 'low',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreign->refresh()->title);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeIncident(User $owner, array $overrides = []): IsmsSecurityIncident {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsSecurityIncident::factory()->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }
}
