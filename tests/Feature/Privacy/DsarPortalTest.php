<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarPortalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Mail\Privacy\DsarReceiptMail;
use App\Models\{Organization, User};
use App\Models\Privacy\{DataSubjectRequest, DsarPortal, PrivacyAttachment};
use App\Services\Privacy\{DataProtectionPermissions, DsarPortalIntakeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Betroffenen-Selbstmeldeportal (Feature 043, MVP-728, G11): Default-Deny,
 * keine Enumeration, Anlage als Portal-Eingang, Eingangsbestaetigung mit
 * Adressbestaetigung ohne Fristwirkung, Anhang-Limits und Mandantentrennung.
 */
class DsarPortalTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function portal(bool $enabled = true, ?Organization $org = null): DsarPortal {
        $org ??= Organization::factory()->create();

        return DsarPortal::create([
            'organization_id' => $org->id,
            'public_slug' => 'ds-' . $org->id . 'abc',
            'is_enabled' => $enabled,
            'allow_attachments' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array {
        return array_merge([
            'type' => 'access',
            'full_name' => 'Erika Musterfrau',
            'email' => 'erika@example.test',
            'message' => 'Ich bitte um Auskunft über die zu meiner Person gespeicherten Daten.',
            'privacy_ack' => '1',
        ], $overrides);
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    // ── Default-Deny / Enumeration ──────────────────────────────────────────

    public function test_landing_page_is_public_and_leaks_no_organization(): void {
        $org = Organization::factory()->create(['name' => 'Streng Geheime Firma GmbH']);
        $this->portal(true, $org);

        $this->get('/datenschutz/anfrage')
            ->assertOk()
            ->assertDontSee('Streng Geheime Firma GmbH')
            ->assertDontSee('ds-' . $org->id . 'abc');
    }

    public function test_unknown_or_disabled_portal_returns_404(): void {
        $this->get('/datenschutz/anfrage/gibt-es-nicht')->assertNotFound();

        $disabled = $this->portal(false);
        $this->get('/datenschutz/anfrage/' . $disabled->public_slug)->assertNotFound();
        $this->post('/datenschutz/anfrage/' . $disabled->public_slug, $this->payload())->assertNotFound();
    }

    public function test_portal_is_hidden_when_the_module_is_not_licensed(): void {
        $org = Organization::factory()->free()->create();
        $portal = $this->portal(true, $org);

        $this->get('/datenschutz/anfrage/' . $portal->public_slug)->assertNotFound();
    }

    public function test_enabled_portal_renders_form_and_strict_headers(): void {
        $portal = $this->portal();

        $res = $this->get('/datenschutz/anfrage/' . $portal->public_slug);
        $res->assertOk()->assertSee(__('dsar.form.submit'));
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("default-src 'none'", (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'none'", (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    // ── Anlage ──────────────────────────────────────────────────────────────

    public function test_submission_creates_a_portal_request_with_encrypted_identity(): void {
        Mail::fake();
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload())
            ->assertRedirect(route('dsar.receipt', ['portal' => $portal->public_slug]));

        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($portal->organization_id, $dsr->organization_id);
        $this->assertSame(DataSubjectRequest::CHANNEL_PORTAL, $dsr->channel);
        $this->assertTrue($dsr->isFromPortal());
        $this->assertNull($dsr->getAttribute('created_by'), 'Portal-Eingang ist actorlos.');
        $this->assertStringContainsString('Erika Musterfrau', (string) $dsr->subject_ciphertext);
        $this->assertSame('erika@example.test', $dsr->contact_email_ciphertext);

        // Klartext liegt NICHT in der Spalte.
        $raw = DB::table('privacy_data_subject_requests')->where('id', $dsr->id)->first();
        $this->assertStringNotContainsString('Erika Musterfrau', (string) $raw->subject_ciphertext);
        $this->assertStringNotContainsString('erika@example.test', (string) $raw->contact_email_ciphertext);

        $this->assertDatabaseHas('privacy_request_events', ['request_id' => $dsr->id, 'event' => 'portal_submitted']);
    }

    public function test_deadline_runs_from_receipt_not_from_the_address_confirmation(): void {
        Mail::fake();
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload());

        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($dsr->received_at);
        $this->assertNotNull($dsr->deadline_at);
        $this->assertNull($dsr->contact_email_confirmed_at);
        $this->assertSame(
            (int) config('dataprotection.dsr_deadline_days'),
            (int) $dsr->received_at->diffInDays($dsr->deadline_at),
        );

        $deadlineBefore = $dsr->deadline_at->toIso8601String();
        $this->get(app(DsarPortalIntakeService::class)->confirmationUrl($dsr))->assertOk();

        $dsr->refresh();
        $this->assertNotNull($dsr->contact_email_confirmed_at);
        $this->assertSame($deadlineBefore, $dsr->deadline_at->toIso8601String(), 'Bestätigung verschiebt die Frist nicht.');
        $this->assertDatabaseHas('privacy_request_events', ['request_id' => $dsr->id, 'event' => 'portal_email_confirmed']);
    }

    public function test_confirmation_requires_a_valid_signature(): void {
        Mail::fake();
        $portal = $this->portal();
        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload());
        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();

        $this->get(route('dsar.confirm', ['dsr' => $dsr->sqid]))->assertForbidden();
        $this->assertNull($dsr->refresh()->contact_email_confirmed_at);
    }

    public function test_receipt_mail_is_queued_to_the_given_address(): void {
        Mail::fake();
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload());

        Mail::assertQueued(DsarReceiptMail::class, static fn (DsarReceiptMail $mail): bool => $mail->hasTo('erika@example.test'));
        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertDatabaseHas('privacy_request_events', ['request_id' => $dsr->id, 'event' => 'portal_receipt_sent']);
    }

    public function test_honeypot_is_silently_accepted_without_creating_a_case(): void {
        Mail::fake();
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload(['company_website' => 'https://spam.test']))
            ->assertRedirect(route('dsar.receipt', ['portal' => $portal->public_slug]));

        $this->assertSame(0, DataSubjectRequest::withoutGlobalScopes()->count());
        Mail::assertNothingQueued();
    }

    public function test_validation_rejects_incomplete_submissions(): void {
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload(['email' => 'keine-mail', 'privacy_ack' => null]))
            ->assertSessionHasErrors(['email', 'privacy_ack']);
        $this->assertSame(0, DataSubjectRequest::withoutGlobalScopes()->count());
    }

    // ── Anhaenge ────────────────────────────────────────────────────────────

    public function test_attachments_are_stored_at_the_case(): void {
        Mail::fake();
        Storage::fake('local');
        $portal = $this->portal();

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload([
            'attachments' => [UploadedFile::fake()->create('nachweis.pdf', 20, 'application/pdf')],
        ]))->assertRedirect();

        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();
        $attachment = PrivacyAttachment::withoutGlobalScopes()
            ->where('attachable_id', $dsr->id)
            ->firstOrFail();
        $this->assertSame('nachweis.pdf', $attachment->filename);
        $this->assertNull($attachment->uploaded_by);
    }

    public function test_attachments_respect_the_portal_ticket_limits(): void {
        Storage::fake('local');
        $portal = $this->portal();

        $tooMany = array_map(
            static fn (int $i): UploadedFile => UploadedFile::fake()->create("datei-$i.pdf", 5, 'application/pdf'),
            range(1, 6),
        );
        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload(['attachments' => $tooMany]))
            ->assertSessionHasErrors('attachments');

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload([
            'attachments' => [UploadedFile::fake()->create('schaedlich.exe', 5, 'application/x-msdownload')],
        ]))->assertSessionHasErrors('attachments');

        $this->assertSame(0, DataSubjectRequest::withoutGlobalScopes()->count());
    }

    public function test_submissions_are_throttled(): void {
        Mail::fake();
        $portal = $this->portal();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload())->assertRedirect();
        }

        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload())->assertStatus(429);
    }

    // ── Mandantentrennung / interne Sicht ───────────────────────────────────

    public function test_request_belongs_to_the_portal_organization_only(): void {
        Mail::fake();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $portalA = $this->portal(true, $orgA);
        $this->portal(true, $orgB);

        $this->post('/datenschutz/anfrage/' . $portalA->public_slug, $this->payload());

        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($orgA->id, $dsr->organization_id);

        $officerB = $this->officer($orgB);
        $this->actingAs($officerB)->get(route('dataprotection.requests.show', $dsr))->assertNotFound();
    }

    public function test_identity_check_is_required_before_the_subject_export(): void {
        Mail::fake();
        Storage::fake('local');
        $org = Organization::factory()->create();
        $portal = $this->portal(true, $org);
        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload());
        $dsr = DataSubjectRequest::withoutGlobalScopes()->firstOrFail();

        $officer = $this->officer($org);
        $employee = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($officer)
            ->post(route('dataprotection.requests.subject-export', $dsr), ['subject_type' => 'user', 'subject_id' => $employee->sqid])
            ->assertSessionHasErrors('identity');
        $this->assertSame(0, PrivacyAttachment::withoutGlobalScopes()->count());

        $this->actingAs($officer)->post(route('dataprotection.requests.verify', $dsr))->assertRedirect();
        $this->actingAs($officer)
            ->post(route('dataprotection.requests.subject-export', $dsr), ['subject_type' => 'user', 'subject_id' => $employee->sqid])
            ->assertSessionHasNoErrors();
    }

    public function test_internal_view_marks_the_portal_intake(): void {
        Mail::fake();
        $org = Organization::factory()->create();
        $portal = $this->portal(true, $org);
        $this->post('/datenschutz/anfrage/' . $portal->public_slug, $this->payload());

        $officer = $this->officer($org);
        $this->actingAs($officer)
            ->get(route('dataprotection.requests.index'))
            ->assertOk()
            ->assertSee(__('dsar.internal.from_portal'));
    }

    // ── Portal-Verwaltung ───────────────────────────────────────────────────

    public function test_portal_settings_require_the_dedicated_permission(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($plain)->get(route('dataprotection.portal.edit'))->assertForbidden();
    }

    public function test_officer_creates_and_rotates_the_portal_link(): void {
        $org = Organization::factory()->create();
        $officer = $this->officer($org);

        $this->actingAs($officer)
            ->put(route('dataprotection.portal.update'), ['is_enabled' => '1', 'allow_attachments' => '1'])
            ->assertRedirect(route('dataprotection.portal.edit'));

        $portal = DsarPortal::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($org->id, $portal->organization_id);
        $this->assertTrue($portal->is_enabled);
        $first = $portal->public_slug;

        $this->actingAs($officer)->post(route('dataprotection.portal.rotate'))->assertRedirect();
        $this->assertNotSame($first, DsarPortal::withoutGlobalScopes()->firstOrFail()->public_slug);
        $this->get('/datenschutz/anfrage/' . $first)->assertNotFound();
    }
}
