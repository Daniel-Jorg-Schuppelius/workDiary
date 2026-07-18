<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicPortalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Organization;
use App\Models\Whistleblowing\{Portal, WhistleblowingCase};
use App\Services\Whistleblowing\ReporterCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;

/**
 * Oeffentliches Meldeportal (Phase 2): Erreichbarkeit, Security-Header,
 * PII-freie Meldung, einmalige Zugangsdaten, Upload-Quarantaene.
 */
class PublicPortalTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    private function portal(bool $enabled = true): Portal {
        $org = Organization::factory()->create();

        return Portal::create([
            'organization_id' => $org->id,
            'public_slug' => 'acme-' . $org->id,
            'is_enabled' => $enabled,
            'allow_anonymous' => true,
            'allow_confidential' => true,
        ]);
    }

    private function payload(array $overrides = []): array {
        return array_merge([
            'reporter_mode' => 'anonymous',
            'category' => 'fraud',
            'subject' => 'Geheimer Betreff zur Sache',
            'description' => 'Ausfuehrliche vertrauliche Beschreibung des Vorfalls.',
            'consent' => '1',
        ], $overrides);
    }

    public function test_landing_page_is_public_and_leaks_no_organization(): void {
        $org = Organization::factory()->create(['name' => 'Streng Geheime Firma GmbH']);
        Portal::create([
            'organization_id' => $org->id,
            'public_slug' => 'wb-geheimer-slug',
            'is_enabled' => true,
            'allow_anonymous' => true,
            'allow_confidential' => true,
        ]);

        $this->get('/melden')
            ->assertOk()
            ->assertSee('Hinweisgeber-Meldeportal')
            ->assertSee(route('whistleblowing.mailbox.login'), false)
            ->assertDontSee('Streng Geheime Firma GmbH')
            ->assertDontSee('wb-geheimer-slug');
    }

    public function test_unknown_or_disabled_portal_returns_404(): void {
        $this->get('/melden/does-not-exist')->assertNotFound();

        $disabled = $this->portal(false);
        $this->get('/melden/' . $disabled->public_slug)->assertNotFound();
    }

    public function test_enabled_portal_renders_form(): void {
        $portal = $this->portal();

        $this->get('/melden/' . $portal->public_slug)
            ->assertOk()
            ->assertSee('Meldung absenden');
    }

    public function test_strict_security_headers_present(): void {
        $portal = $this->portal();
        $res = $this->get('/melden/' . $portal->public_slug);

        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("default-src 'none'", (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $res->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_submission_creates_case_without_pii_and_records_event(): void {
        $portal = $this->portal();

        $this->post('/melden/' . $portal->public_slug, $this->payload())
            ->assertRedirect(route('whistleblowing.receipt', ['portal' => $portal->public_slug]));

        $case = WhistleblowingCase::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($portal->organization_id, $case->organization_id);
        $this->assertSame('anonymous', $case->reporter_mode->value);

        // Keine Reporter-Identitaet in der Tabelle.
        $columns = Schema::getColumnListing('whistleblowing_cases');
        $this->assertNotContains('reporter_user_id', $columns);
        $this->assertNotContains('ip', $columns);

        // Inhalt verschluesselt at rest.
        $raw = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertStringNotContainsString('Geheimer Betreff', (string) $raw->subject_ciphertext);

        // Event geschrieben.
        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $case->id,
            'event' => 'case.submitted',
        ]);
    }

    public function test_credentials_are_shown_once_and_stored_hashed(): void {
        $portal = $this->portal();

        $this->post('/melden/' . $portal->public_slug, $this->payload());

        $secret = session('wb_secret');
        $caseNumber = session('wb_case_number');
        $this->assertIsString($secret);
        $this->assertIsString($caseNumber);

        $case = WhistleblowingCase::withoutGlobalScopes()->firstOrFail();
        $cred = app(ReporterCredentialService::class);

        // Geheimnis ist NICHT im Klartext gespeichert, aber verifizierbar.
        $this->assertNotSame($secret, $case->getAttribute('access_code_hash'));
        $this->assertTrue($cred->verifySecret($secret, (string) $case->getAttribute('access_code_hash')));

        // Receipt zeigt das Geheimnis (einmalig via Flash).
        $this->get('/melden/' . $portal->public_slug . '/erfolg')
            ->assertOk()
            ->assertSee($secret);
    }

    public function test_confidential_mode_encrypts_contact(): void {
        $portal = $this->portal();

        $this->post('/melden/' . $portal->public_slug, $this->payload([
            'reporter_mode' => 'confidential',
            'contact' => 'reporter@example.org',
        ]))->assertRedirect();

        $case = WhistleblowingCase::withoutGlobalScopes()->firstOrFail();
        $raw = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertNotNull($raw->contact_ciphertext);
        $this->assertStringNotContainsString('reporter@example.org', (string) $raw->contact_ciphertext);
        $this->assertSame('reporter@example.org', $case->contact_ciphertext);
    }

    public function test_invalid_submission_is_rejected(): void {
        $portal = $this->portal();

        $this->post('/melden/' . $portal->public_slug, $this->payload([
            'subject' => '',
            'consent' => null,
        ]))->assertSessionHasErrors(['subject', 'consent']);

        $this->assertSame(0, WhistleblowingCase::withoutGlobalScopes()->count());
    }

    public function test_uploaded_attachment_is_quarantined_and_encrypted(): void {
        Storage::fake('whistleblowing');
        $portal = $this->portal();

        $file = UploadedFile::fake()->create('beweis.pdf', 50, 'application/pdf');

        $this->post('/melden/' . $portal->public_slug, $this->payload([
            'attachments' => [$file],
        ]))->assertRedirect();

        $case = WhistleblowingCase::withoutGlobalScopes()->firstOrFail();
        $attachment = $case->attachments()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame('pending', $attachment->scan_status->value, 'Anhang startet in Quarantaene.');
        $this->assertFalse((bool) $attachment->metadata_scrubbed);
        Storage::disk('whistleblowing')->assertExists($attachment->storage_key);

        // Originalname verschluesselt at rest, aber lesbar via Cast.
        $raw = DB::table('whistleblowing_attachments')->where('id', $attachment->id)->first();
        $this->assertStringNotContainsString('beweis.pdf', (string) $raw->original_name_ciphertext);
    }
}
