<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingChecklistResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Onboarding;

use App\Enums\Protocol\ProtocolStatus;
use App\Models\{AuditLog, Classification, Customer, DiaryEntry, OnboardingProgress, Organization, Project, Protocol, TimeEntry, User};
use App\Services\Onboarding\OnboardingChecklistResolver;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class OnboardingChecklistResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_resolver_marks_core_steps_and_syncs_progress_rows(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = 'it_service';
        $this->organization->settings = $settings;
        $this->organization->save();

        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $admin->id,
        ]);

        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Onboarding-Projekt',
            'status' => \App\Enums\Project\ProjectStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        Classification::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => \App\Enums\Classification\ClassificationDomain::EntryType->value,
            'code' => 'wartung',
            'label' => 'Wartung',
            'sort_order' => 10,
            'active' => true,
        ]);

        $subject = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
        ]);

        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ProtocolStatus::Signed->value,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $subject->id,
            'created_by_user_id' => $admin->id,
            'signed_at' => now(),
        ]);

        AuditLog::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'event' => 'backup.completed',
            'auditable_type' => Organization::class,
            'auditable_id' => $this->organization->id,
            'changes' => ['status' => 'ok'],
        ]);

        $resolver = app(OnboardingChecklistResolver::class);
        $result = $resolver->forOrganization($this->organization, $admin);

        $this->assertTrue($result['all_required_done']);
        $this->assertSame($result['required_total'], $result['required_done']);
        $this->assertSame(100, $result['progress_percent']);

        $doneStepCodes = collect($result['steps'])
            ->filter(static fn(array $step): bool => $step['done'])
            ->pluck('code')
            ->all();

        $this->assertContains('org.profile', $doneStepCodes);
        $this->assertContains('org.branch_profile', $doneStepCodes);
        $this->assertContains('users.invite', $doneStepCodes);
        $this->assertContains('roles.check', $doneStepCodes);
        $this->assertContains('customer.first', $doneStepCodes);
        $this->assertContains('work.first', $doneStepCodes);
        $this->assertContains('time.first', $doneStepCodes);
        $this->assertContains('protocol.first_signed', $doneStepCodes);
        $this->assertContains('backup.heartbeat', $doneStepCodes);

        $this->assertDatabaseHas('onboarding_progress', [
            'organization_id' => $this->organization->id,
            'step_code' => 'org.profile',
            'state' => 'done',
        ]);
        $this->assertDatabaseHas('onboarding_progress', [
            'organization_id' => $this->organization->id,
            'step_code' => 'backup.heartbeat',
            'state' => 'done',
        ]);
    }

    public function test_resolver_keeps_skipped_step_when_condition_is_still_open(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        OnboardingProgress::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'step_code' => 'time.first',
            'state' => 'skipped',
            'skipped_reason' => 'Wird später erledigt',
        ]);

        $resolver = app(OnboardingChecklistResolver::class);
        $resolver->forOrganization($this->organization, $admin);

        $row = OnboardingProgress::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('step_code', 'time.first')
            ->firstOrFail();

        $this->assertSame('skipped', $row->state);
        $this->assertSame('Wird später erledigt', $row->skipped_reason);
    }

    public function test_resolver_writes_step_completed_audit_only_on_transition_to_done(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $resolver = app(OnboardingChecklistResolver::class);
        $resolver->forOrganization($this->organization, $admin);

        $firstRunCount = AuditLog::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', 'onboarding.stepCompleted')
            ->where('changes->step_code', 'org.profile')
            ->count();
        $this->assertSame(1, $firstRunCount, 'org.profile sollte beim ersten Lauf als completed protokolliert werden.');

        // Zweiter Lauf ohne Zustandsänderung — kein neues Event.
        $resolver->forOrganization($this->organization, $admin);

        $secondRunCount = AuditLog::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', 'onboarding.stepCompleted')
            ->where('changes->step_code', 'org.profile')
            ->count();
        $this->assertSame(1, $secondRunCount, 'stepCompleted darf nur beim Übergang open→done geschrieben werden.');
    }

    public function test_resolver_writes_completed_audit_when_all_required_steps_done_first_time(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        // Operator-Rolle in derselben Org, damit roles.check (Admin + User) erfüllt ist.
        User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = 'it_service';
        $this->organization->settings = $settings;
        $this->organization->save();

        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $admin->id,
        ]);

        Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Onboarding-Projekt',
            'status' => \App\Enums\Project\ProjectStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        $resolver = app(OnboardingChecklistResolver::class);
        $result = $resolver->forOrganization($this->organization, $admin);

        $this->assertTrue($result['all_required_done']);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'onboarding.completed',
            'auditable_type' => Organization::class,
            'auditable_id' => $this->organization->id,
        ]);

        // Zweiter Lauf — onboarding.completed darf nicht erneut geschrieben werden.
        $resolver->forOrganization($this->organization, $admin);
        $completedCount = AuditLog::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', 'onboarding.completed')
            ->count();
        $this->assertSame(1, $completedCount, 'onboarding.completed darf nur einmal pro Übergang geschrieben werden.');
    }
}
