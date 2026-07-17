<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Crisis;

use App\Enums\User\UserRole;
use App\Models\Crisis\{CrisisCase, CrisisDeadlineTemplate, CrisisRole};
use App\Models\{Organization, User};
use App\Services\Crisis\{CrisisAlertService, CrisisDeadlineService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 070, MVP-212–221: Krisenakte — Alarmierung mit Quittierung und
 * Stellvertreter-Eskalation (D7), append-only Lagebild, getrennte
 * Kommunikationsstufen (Entwurf/Freigabe/Aussendung, keine
 * Selbstfreigabe), Meldefristen aus Katalogdaten (D9) und
 * Stab-Notfallzugriff; Rechte-/Tenant-Schutz.
 */
final class CrisisLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function makeCase(string $category = 'security'): CrisisCase {
        return CrisisCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Ransomware-Verdacht',
            'category' => $category,
            'severity' => 'critical',
            'status' => 'reported',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_alerting_with_acknowledgement_and_deputy_escalation(): void {
        Notification::fake();
        $case = $this->makeCase();
        $role = CrisisRole::query()->create(['organization_id' => $this->organization->id, 'name' => 'Leitung Krisenstab']);
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $deputy = User::factory()->create(['organization_id' => $this->organization->id]);

        $assignment = $case->team()->create([
            'organization_id' => $this->organization->id,
            'crisis_role_id' => $role->id,
            'user_id' => $member->id,
            'deputy_user_id' => $deputy->id,
        ]);

        $alerts = app(CrisisAlertService::class);
        $this->assertSame(1, $alerts->alert($case, $this->admin));
        $this->assertNotNull($assignment->fresh()->alerted_at);
        Notification::assertSentToTimes($member, \App\Notifications\GenericEventNotification::class, 1);

        // Eskalation: Mitglied + Stellvertretung erneut.
        $this->assertSame(1, $alerts->escalate($case, $this->admin));
        Notification::assertSentToTimes($deputy, \App\Notifications\GenericEventNotification::class, 1);
        $this->assertNotNull($assignment->fresh()->deputy_alerted_at);

        // Fremde dürfen nicht quittieren; die Stellvertretung schon.
        try {
            $alerts->acknowledge($assignment->fresh(), $this->admin);
            $this->fail('Fremd-Quittierung akzeptiert.');
        } catch (\RuntimeException) {
        }
        $alerts->acknowledge($assignment->fresh(), $deputy);
        $this->assertNotNull($assignment->fresh()->acknowledged_at);

        // Quittierte werden nicht erneut alarmiert.
        $this->assertSame(0, $alerts->alert($case->refresh(), $this->admin));
    }

    public function test_deadlines_resolve_from_catalog_with_org_override(): void {
        CrisisDeadlineTemplate::query()->create([
            'organization_id' => null, 'category' => 'security',
            'label' => 'NIS2 Frühwarnung', 'offset_hours' => 24, 'source' => '§ 32 BSIG', 'active' => true,
        ]);
        CrisisDeadlineTemplate::query()->create([
            'organization_id' => null, 'category' => 'security',
            'label' => 'NIS2 Meldung', 'offset_hours' => 72, 'source' => '§ 32 BSIG', 'active' => true,
        ]);

        $case = $this->makeCase();
        $case->update(['status' => 'activated', 'activated_at' => now()]);

        $service = app(CrisisDeadlineService::class);
        $deadlines = $service->deadlinesFor($case->refresh());
        $this->assertCount(2, $deadlines);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $deadlines[0]['due_at']->timestamp, 5);

        // Org-Override gewinnt (D9: Datenpflege statt Release).
        CrisisDeadlineTemplate::query()->create([
            'organization_id' => $this->organization->id, 'category' => 'security',
            'label' => 'Interne Sofortmeldung', 'offset_hours' => 4, 'source' => 'Org-Richtlinie', 'active' => true,
        ]);
        $overridden = $service->deadlinesFor($case->refresh());
        $this->assertCount(1, $overridden);
        $this->assertSame('Interne Sofortmeldung', $overridden[0]['label']);
    }

    public function test_situation_reports_are_versioned_and_communication_stages_are_separated(): void {
        $case = $this->makeCase();
        $second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Lagebild: Versionen zählen hoch (append-only).
        $this->actingAs($this->admin)->post(route('crisis.sitrep.store', $case), ['content' => 'Erste Lage'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('crisis.sitrep.store', $case), ['content' => 'Zweite Lage'])->assertRedirect();
        $this->assertSame([2, 1], $case->situationReports()->pluck('version')->all());

        // Kommunikation: Entwurf → Freigabe (nie selbst) → Aussendung.
        $this->actingAs($this->admin)->post(route('crisis.communications.store', $case), [
            'audience' => 'customers', 'subject' => 'Störungsinfo', 'body' => 'Wir arbeiten daran.',
        ])->assertRedirect();
        $communication = $case->communications()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('crisis.communications.sent', [$case, $communication]), ['channel' => 'Mail'])
            ->assertSessionHas('error'); // ohne Freigabe keine Aussendung
        $this->actingAs($this->admin)
            ->post(route('crisis.communications.approve', [$case, $communication]))
            ->assertSessionHas('error'); // Selbstfreigabe gesperrt

        $this->actingAs($second)->post(route('crisis.communications.approve', [$case, $communication]))->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('crisis.communications.sent', [$case, $communication]), ['channel' => 'Mail'])->assertSessionHas('status');
        $fresh = $communication->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertNotSame((int) $fresh->created_by, (int) $fresh->approved_by);
    }

    public function test_close_requires_review_and_member_break_glass_access(): void {
        $case = $this->makeCase();
        $case->update(['status' => 'activated', 'activated_at' => now()]);

        // Stabsmitglied OHNE crisis-Rechte sieht SEINE Akte (Notfallzugriff).
        $member = $this->userWithRole(UserRole::User->value);
        $this->actingAs($member)->get(route('crisis.show', $case))->assertForbidden();
        $role = CrisisRole::query()->create(['organization_id' => $this->organization->id, 'name' => 'IT']);
        $case->team()->create([
            'organization_id' => $this->organization->id,
            'crisis_role_id' => $role->id,
            'user_id' => $member->id,
        ]);
        $this->actingAs($member)->get(route('crisis.show', $case))->assertOk();
        // Aber keine Liste (viewAny) und keine Führung.
        $this->actingAs($member)->get(route('crisis.index'))->assertForbidden();

        // Abschluss erst nach Entwarnung + Nachbereitung.
        $this->actingAs($this->admin)->post(route('crisis.close', $case))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.all-clear', $case))->assertSessionHas('status');
        $this->actingAs($this->admin)->post(route('crisis.review.store', $case), ['summary' => 'Verlauf + Lessons'])->assertSessionHas('status');
        $this->assertSame('post_review', $case->fresh()->status);
        $this->actingAs($this->admin)->post(route('crisis.close', $case))->assertSessionHas('status');
        $this->assertSame('closed', $case->fresh()->status);

        // Fremde Organisation: 404.
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreign)->get(route('crisis.show', $case))->assertNotFound();
    }

    public function test_teamleitung_reads_but_cannot_manage(): void {
        $case = $this->makeCase();
        $lead = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($lead)->get(route('crisis.index'))->assertOk();
        $this->actingAs($lead)->get(route('crisis.show', $case))->assertOk();
        $this->actingAs($lead)->post(route('crisis.sitrep.store', $case), ['content' => 'x'])->assertForbidden();
        $this->actingAs($lead)->post(route('crisis.activate', $case))->assertForbidden();
    }

    public function test_exercise_dialogs_plan_and_document(): void {
        $this->actingAs($this->admin)->get(route('crisis.exercises.index'))->assertOk();

        // Plan-Dialog + Anlage.
        $this->actingAs($this->admin)->get(route('crisis.exercises.create'))
            ->assertOk()
            ->assertSee('Übung planen');
        $this->actingAs($this->admin)->post(route('crisis.exercises.store'), [
            'title' => 'Ausfall Rechenzentrum',
            'scenario' => 'Stromausfall legt das primäre RZ lahm.',
        ])->assertSessionHas('status');

        $exercise = \App\Models\Crisis\CrisisExercise::query()->firstOrFail();

        // Dokumentieren-Dialog + Dokumentation mit allen Feldern.
        $this->actingAs($this->admin)->get(route('crisis.exercises.document.form', $exercise))
            ->assertOk()
            ->assertSee('Übung dokumentieren');
        $this->actingAs($this->admin)->post(route('crisis.exercises.document', $exercise), [
            'participants' => 'Krisenstab, IT-Betrieb',
            'observations' => 'Failover nach 20 Minuten.',
            'deviations' => 'Alarmierungskette unvollständig.',
            'effectiveness' => 'partly',
            'follow_up' => 'Playbook um Eskalationspfad ergänzen.',
        ])->assertSessionHas('status');
        $this->assertNotNull($exercise->fresh()->exercised_at);

        // Ohne Führungsrechte weder planen noch dokumentieren.
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->get(route('crisis.exercises.create'))->assertForbidden();
        $this->actingAs($lead)->get(route('crisis.exercises.document.form', $exercise))->assertForbidden();
    }
}
