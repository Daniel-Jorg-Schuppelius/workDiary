<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicCareerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Careers;

use App\Models\Applications\{JobApplication, JobPosting, JobRequisition};
use App\Services\Applications\CareerFormState;
use App\Services\Licensing\FeatureFlagResolver;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-437 — Öffentlicher Karrierebereich + sessionloser Bewerbungseingang.
 */
class PublicCareerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['license.feature_overrides' => ['module.applications' => true]]);
        app(FeatureFlagResolver::class)->flush();
        Storage::fake('local');
    }

    private function enablePortal(): void {
        Setting::set('applications.portal.enabled', true, SettingScope::Organization, $this->organization);
    }

    private function publishPosting(array $overrides = []): JobPosting {
        $requisition = JobRequisition::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'title' => 'Servicetechniker (m/w/d)',
            'profile' => 'INTERN: Budget 60k, Stellenprofil vertraulich.',
            'budget_note' => 'INTERN: 60000',
            'status' => 'open',
        ], $overrides['requisition'] ?? []));

        return JobPosting::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'job_requisition_id' => $requisition->id,
            'channel' => 'website',
            'status' => 'published',
            'published_at' => now(),
            'public_slug' => 'servicetechniker',
            'public_title' => 'Servicetechniker (m/w/d)',
            'public_summary' => 'Wartung und Montage beim Kunden.',
            'public_description' => 'Öffentliche Beschreibung.',
            'work_location' => 'Berlin',
        ], $overrides['posting'] ?? []));
    }

    public function test_portal_disabled_returns_404(): void {
        $this->publishPosting();

        $this->get('/karriere/' . $this->organization->slug)->assertNotFound();
    }

    public function test_lists_published_postings_and_hides_drafts(): void {
        $this->enablePortal();
        $this->publishPosting();
        $this->publishPosting(['posting' => ['public_slug' => 'entwurf', 'status' => 'draft', 'public_title' => 'Geheim-Entwurf']]);

        $this->get('/karriere/' . $this->organization->slug)
            ->assertOk()
            ->assertSee('Servicetechniker (m/w/d)')
            ->assertDontSee('Geheim-Entwurf');
    }

    public function test_detail_shows_public_content_but_not_internal_fields(): void {
        $this->enablePortal();
        $this->publishPosting();

        $this->get('/karriere/' . $this->organization->slug . '/stellen/servicetechniker')
            ->assertOk()
            ->assertSee('Öffentliche Beschreibung.')
            ->assertSee('Berlin')
            ->assertDontSee('INTERN');
    }

    public function test_public_apply_creates_actorless_application(): void {
        $this->enablePortal();
        $posting = $this->publishPosting();

        $token = CareerFormState::issue($posting->id, time());

        $this->post('/karriere/' . $this->organization->slug . '/stellen/servicetechniker/bewerben', [
            'form_state' => $token,
            'candidate_name' => 'Erika Muster',
            'email' => 'erika@example.com',
            'phone' => '030 12345',
            'message' => 'Ich bewerbe mich.',
            'privacy_ack' => '1',
        ])->assertRedirect(route('careers.confirmation', ['org' => $this->organization->slug, 'posting' => 'servicetechniker']));

        $application = JobApplication::query()->firstOrFail();
        $this->assertSame('website', $application->source);
        $this->assertSame('received', $application->status);
        $this->assertNull($application->created_by);
        $this->assertNotNull($application->privacy_ack_at);
        $this->assertSame($posting->id, $application->job_posting_id);
        $this->assertSame('erika@example.com', $application->email);
    }

    public function test_honeypot_submission_creates_no_application(): void {
        $this->enablePortal();
        $posting = $this->publishPosting();
        $token = CareerFormState::issue($posting->id, time());

        $this->post('/karriere/' . $this->organization->slug . '/stellen/servicetechniker/bewerben', [
            'form_state' => $token,
            'candidate_name' => 'Bot',
            'email' => 'bot@example.com',
            'privacy_ack' => '1',
            'company_website' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_duplicate_submission_is_idempotent(): void {
        $this->enablePortal();
        $posting = $this->publishPosting();
        $token = CareerFormState::issue($posting->id, time());
        $payload = [
            'form_state' => $token,
            'candidate_name' => 'Erika Muster',
            'email' => 'erika@example.com',
            'privacy_ack' => '1',
        ];
        $url = '/karriere/' . $this->organization->slug . '/stellen/servicetechniker/bewerben';

        $this->post($url, $payload)->assertRedirect();
        $this->post($url, $payload)->assertRedirect();

        $this->assertSame(1, JobApplication::query()->count());
    }

    public function test_expired_form_state_is_rejected(): void {
        $this->enablePortal();
        $posting = $this->publishPosting();
        $stale = CareerFormState::issue($posting->id, time() - (CareerFormState::TTL + 120));

        $this->post('/karriere/' . $this->organization->slug . '/stellen/servicetechniker/bewerben', [
            'form_state' => $stale,
            'candidate_name' => 'Erika Muster',
            'email' => 'erika@example.com',
            'privacy_ack' => '1',
        ])->assertRedirect(route('careers.show', ['org' => $this->organization->slug, 'posting' => 'servicetechniker']));

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_uploads_are_quarantined(): void {
        $this->enablePortal();
        $posting = $this->publishPosting();
        $token = CareerFormState::issue($posting->id, time());

        $this->post('/karriere/' . $this->organization->slug . '/stellen/servicetechniker/bewerben', [
            'form_state' => $token,
            'candidate_name' => 'Erika Muster',
            'email' => 'erika@example.com',
            'privacy_ack' => '1',
            'documents' => [UploadedFile::fake()->create('lebenslauf.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $this->assertDatabaseHas('job_application_uploads', [
            'organization_id' => $this->organization->id,
            'scan_status' => 'pending',
        ]);
    }

    public function test_ensure_public_slug_is_unique_per_org(): void {
        $requisition = JobRequisition::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Techniker', 'status' => 'open',
        ]);
        JobPosting::query()->create([
            'organization_id' => $this->organization->id, 'job_requisition_id' => $requisition->id,
            'channel' => 'website', 'status' => 'published', 'public_slug' => 'techniker', 'public_title' => 'Techniker',
        ]);

        $fresh = new JobPosting(['organization_id' => $this->organization->id]);
        $this->assertSame('techniker-2', $fresh->ensurePublicSlug('Techniker'));
    }

    public function test_admin_can_publish_posting_to_career_area(): void {
        $admin = $this->orgAdmin();
        $requisition = JobRequisition::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Monteur', 'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->post(route('recruiting.requisitions.career.publish', $requisition), [
                'public_title' => 'Monteur (m/w/d)',
                'public_summary' => 'Montage vor Ort.',
                'public_description' => 'Beschreibung.',
            ])->assertRedirect();

        $this->assertDatabaseHas('job_postings', [
            'job_requisition_id' => $requisition->id,
            'channel' => 'website',
            'status' => 'published',
            'public_slug' => 'monteur-mwd',
        ]);
    }

    public function test_embed_response_allows_configured_origin_but_canonical_does_not(): void {
        $this->enablePortal();
        Setting::set('applications.portal.embed_origins', "https://kunde.example\nhttp://unsicher.example", SettingScope::Organization, $this->organization);
        $this->publishPosting();

        $embed = $this->get('/karriere/' . $this->organization->slug . '/stellen/servicetechniker/embed')->assertOk();
        $csp = $embed->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors https://kunde.example', (string) $csp);
        $this->assertStringNotContainsString('unsicher.example', (string) $csp); // http:// verworfen
        $this->assertFalse($embed->headers->has('X-Frame-Options'));

        $canonical = $this->get('/karriere/' . $this->organization->slug . '/stellen/servicetechniker')->assertOk();
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $canonical->headers->get('Content-Security-Policy'));
        $this->assertSame('SAMEORIGIN', $canonical->headers->get('X-Frame-Options'));
    }
}
