<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingMessageService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Anonymes Postfach (Phase 4): Login nur per Geheimnis, Sichtbarkeit (keine
 * internen Notizen), Zwei-Wege-Kommunikation, Upload-Quarantaene, Logout.
 */
class MailboxTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    /** @return array{0: WhistleblowingCase, 1: string} */
    private function caseWithSecret(): array {
        $org = Organization::factory()->create();
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'Betreff';
        $case->description_ciphertext = 'Beschreibung';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return [$case, $secret];
    }

    public function test_login_with_secret_opens_mailbox(): void {
        [$case, $secret] = $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => $secret])
            ->assertRedirect(route('whistleblowing.mailbox.show'));

        $this->get('/melden/postfach/nachrichten')->assertOk()->assertSee($case->case_number);
    }

    public function test_wrong_secret_fails_without_session(): void {
        $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => 'FALSCH-XXXX-YYYY'])
            ->assertSessionHasErrors('secret');

        $this->assertNull(session('wb_mailbox_case_id'));
    }

    public function test_case_number_is_not_a_login(): void {
        [$case] = $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => $case->case_number])
            ->assertSessionHasErrors('secret');
    }

    public function test_reporter_sees_released_messages_not_internal_notes(): void {
        [$case, $secret] = $this->caseWithSecret();
        $handler = User::factory()->create(['organization_id' => $case->organization_id]);
        $messages = app(WhistleblowingMessageService::class);
        $messages->sendToReporter($case, 'SICHTBARE_ANTWORT', $handler);
        $messages->addInternalNote($case, 'GEHEIME_INTERNE_NOTIZ', $handler);

        $this->post('/melden/postfach', ['secret' => $secret]);

        $this->get('/melden/postfach/nachrichten')
            ->assertOk()
            ->assertSee('SICHTBARE_ANTWORT')
            ->assertDontSee('GEHEIME_INTERNE_NOTIZ');
    }

    public function test_reporter_can_reply(): void {
        [$case, $secret] = $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => $secret]);
        $this->post('/melden/postfach/nachrichten', ['body' => 'ReporterAntwortXYZ'])->assertRedirect();

        $message = $case->messages()->where('author_type', 'reporter')->firstOrFail();
        $this->assertSame('reporter', $message->visibility->value);
        $this->assertSame('ReporterAntwortXYZ', $message->body_ciphertext);
    }

    public function test_reporter_upload_is_quarantined(): void {
        Storage::fake('whistleblowing');
        [$case, $secret] = $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => $secret]);
        $this->post('/melden/postfach/anhaenge', [
            'file' => UploadedFile::fake()->create('nachweis.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $case->attachments()->firstOrFail();
        $this->assertSame('reporter', $attachment->uploaded_by_type);
        $this->assertSame('pending', $attachment->scan_status->value);
    }

    public function test_mailbox_requires_session(): void {
        $this->get('/melden/postfach/nachrichten')
            ->assertRedirect(route('whistleblowing.mailbox.login'));
    }

    public function test_logout_clears_session(): void {
        [, $secret] = $this->caseWithSecret();

        $this->post('/melden/postfach', ['secret' => $secret]);
        $this->post('/melden/postfach/abmelden')->assertRedirect(route('whistleblowing.mailbox.login'));

        $this->get('/melden/postfach/nachrichten')
            ->assertRedirect(route('whistleblowing.mailbox.login'));
    }
}
