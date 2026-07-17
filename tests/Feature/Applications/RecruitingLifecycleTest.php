<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecruitingLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Enums\User\UserRole;
use App\Models\Applications\{JobApplication, JobRequisition};
use App\Models\User;
use App\Services\Applications\RecruitingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 068, MVP-190–193: Bewerbungsakte — verschlüsselte PII mit
 * Dubletten-Hash, Zugriffstrennung (recruiting.*), Datenschutz-Lifecycle
 * (Löschvormerkung, Talentpool-Einwilligung, Anonymisierung, Auskunft)
 * und Onboarding-Übergabe über den Mitarbeiter-Entwurf.
 */
final class RecruitingLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_intake_encrypts_pii_and_flags_duplicates(): void {
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);

        $this->actingAs($hr)->post(route('recruiting.applications.store'), [
            'candidate_name' => 'Kim Beispiel',
            'email' => 'kim@example.test',
            'source' => 'website',
        ])->assertRedirect();

        $application = JobApplication::query()->firstOrFail();
        $this->assertSame('Kim Beispiel', $application->candidate_name);
        // PII liegt verschlüsselt in der DB (kein Klartext).
        $raw = \Illuminate\Support\Facades\DB::table('job_applications')->where('id', $application->id)->first();
        $this->assertNotSame('Kim Beispiel', (string) $raw->candidate_name);
        $this->assertStringNotContainsString('kim@example.test', (string) $raw->email);
        $this->assertSame(JobApplication::hashEmail('kim@example.test'), $application->email_hash);

        // Dublette (gleiche Mail) → Hinweis, keine Blockade.
        $result = app(RecruitingService::class)->intake(['candidate_name' => 'K. B.', 'email' => 'KIM@example.test', 'source' => 'portal'], $hr);
        $this->assertSame(1, $result['duplicates']);
    }

    public function test_privacy_lifecycle_rejection_talentpool_anonymize_export(): void {
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $service = app(RecruitingService::class);

        ['application' => $application] = $service->intake(['candidate_name' => 'A', 'email' => 'a@example.test', 'source' => 'website'], $hr);

        // Absage → Löschvormerkung nach konfigurierter Frist (Default 6 Monate).
        $service->decide($application, 'rejected', null, $hr);
        $fresh = $application->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertNotNull($fresh->retention_until);
        $this->assertTrue($fresh->retention_until->between(now()->addMonths(5), now()->addMonths(7)));

        // Talentpool NUR mit ausdrücklicher Einwilligung.
        ['application' => $second] = $service->intake(['candidate_name' => 'B', 'email' => 'b@example.test', 'source' => 'portal'], $hr);
        try {
            $service->decide($second, 'talent_pool', null, $hr);
            $this->fail('Talentpool ohne Einwilligung akzeptiert.');
        } catch (\RuntimeException) {
        }
        $service->decide($second->refresh(), 'talent_pool', null, $hr, talentPoolConsent: true);
        $this->assertNotNull($second->fresh()->consent_expires_on);

        // Auskunft (Art. 15) enthält die Kandidatendaten.
        $export = $service->export($second->fresh());
        $this->assertSame('B', $export['candidate_name']);

        // Anonymisierung entfernt PII, Akte bleibt als Zähler.
        $this->actingAs($hr)->post(route('recruiting.applications.anonymize', $second))->assertRedirect();
        $anonymized = $second->fresh();
        $this->assertNull($anonymized->candidate_name);
        $this->assertNull($anonymized->email_hash);
        $this->assertSame('deleted', $anonymized->status);
        $this->assertNotNull($anonymized->anonymized_at);
    }

    public function test_recruiting_area_is_isolated_from_normal_roles(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        ['application' => $application] = app(RecruitingService::class)->intake(['candidate_name' => 'C', 'source' => 'other'], $admin);

        // Teamleitung (operative Rolle) hat KEINEN Zugriff auf Bewerberdaten.
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->get(route('recruiting.applications.index'))->assertForbidden();
        $this->actingAs($lead)->get(route('recruiting.applications.show', $application))->assertForbidden();

        // Personalverwaltung schon.
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $this->actingAs($hr)->get(route('recruiting.applications.show', $application))->assertOk();
    }

    public function test_acceptance_creates_draft_and_deliberate_invite_creates_user(): void {
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $requisition = JobRequisition::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Servicetechniker:in',
            'status' => 'open',
        ]);
        ['application' => $application] = app(RecruitingService::class)->intake([
            'job_requisition_id' => $requisition->id,
            'candidate_name' => 'Kim Neu',
            'email' => 'kim.neu@example.test',
            'source' => 'website',
        ], $hr);

        // Entwurf nur nach Zusage.
        $this->actingAs($hr)->post(route('recruiting.applications.draft.store', $application))->assertSessionHas('error');

        app(RecruitingService::class)->decide($application, 'accepted', null, $hr);
        $this->actingAs($hr)->post(route('recruiting.applications.draft.store', $application), [
            'qualifications' => "Elektrofachkraft\nFührerschein B",
        ])->assertSessionHas('status');

        $draft = $application->fresh()->employeeDraft;
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, User::query()->where('email', 'kim.neu@example.test')->count(), 'Entwurf darf KEIN Live-Konto erzeugen.');

        // Bewusste Einladung erzeugt den User über den Invite-Pfad.
        $this->actingAs($hr)->post(route('recruiting.applications.draft.invite', [$application, $draft]))->assertSessionHas('status');
        $user = User::query()->where('email', 'kim.neu@example.test')->firstOrFail();
        $this->assertTrue((bool) $user->is_new_system);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame('invited', $draft->fresh()->status);

        // Idempotent: zweite Einladung schlägt fehl.
        $this->actingAs($hr)->post(route('recruiting.applications.draft.invite', [$application, $draft]))->assertSessionHas('error');
    }
}
