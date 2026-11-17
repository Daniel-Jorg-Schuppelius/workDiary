<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachmentViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Attachments;

use App\Enums\User\Permission;
use App\Http\Controllers\Attachments\{AttachmentController, MailAttachmentController};
use App\Http\Middleware\SecurityHeaders;
use App\Jobs\Attachments\ReadMailAttachmentJob;
use App\Models\Attachments\{Attachment, AttachmentText};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Attachments\Mail\MailAttachmentContents;
use App\Services\Attachments\Search\AttachmentSearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Support\MailFiles;
use Tests\TestCase;

/**
 * Ansicht „Mail“ (Feature 204, MVP-1653) mit den Akzeptanzkriterien 1–4 und
 * den Rechten: die Mail erbt die Sichtbarkeit ihres Trägers, der Anhang-Pfad
 * reicht nie über die eine Datei hinaus.
 */
final class MailAttachmentViewTest extends TestCase {
    use RefreshDatabase;

    private User $owner;

    private DiaryEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->user()->create();
        $this->entry = DiaryEntry::factory()->for($this->owner)->create();
    }

    /** AK 1: .eml mit HTML-Text und PDF-Anhang — Kopf, bereinigter Text, Anhangsliste; das PDF öffnet in der Vorschau. */
    public function test_eml_shows_header_clean_text_and_attachments_and_previews_the_pdf(): void {
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());

        $this->actingAs($this->owner)->get(route('attachments.mail.show', $mail))
            ->assertOk()
            ->assertSee('Angebot für die Übergabe')
            ->assertSee('Erika Mustermann (Büro) <erika.mustermann@example.org>')
            ->assertSee('Max Beispiel <max.beispiel@example.com>')
            ->assertSee('anbei das Angebot für die Übergabe.')
            ->assertSee(__('mail_file.text.from_html'))
            ->assertDontSee('pwned')
            ->assertSee('Angebot Übergabe.pdf')
            ->assertSee('logo.png')
            ->assertSee('Ursprüngliche Anfrage')
            ->assertDontSee('<iframe sandbox', false);

        $preview = $this->actingAs($this->owner)->get(route('attachments.mail.show', [$mail, 'vorschau' => '1']))
            ->assertOk()
            ->assertSee(__('mail_file.attachments.preview_heading', ['name' => 'Angebot Übergabe.pdf']));
        $this->assertMatchesRegularExpression('#<iframe src="[^"]*/attachments/[^"]+/mail/parts/1\?[^"]*inline=1[^"]*signature=#', (string) $preview->getContent());

        $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '1', true))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', SecurityHeaders::DOCUMENT_CSP)
            ->assertContent(MailFiles::PDF);
        $disposition = (string) $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '1', true))->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline;', $disposition);
    }

    /** AK 2: .msg mit Umlauten im Betreff zeigt den Betreff richtig (Unicode und Codepage). */
    public function test_msg_shows_umlaut_subject(): void {
        foreach (['rueckfrage-unicode.msg', 'rueckfrage-ansi.msg'] as $fixture) {
            $mail = $this->storeMail($fixture, MailFiles::fixture($fixture));

            $this->actingAs($this->owner)->get(route('attachments.mail.show', $mail))
                ->assertOk()
                ->assertSee('Rückfrage zur Übergabe – Größe & Maße')
                ->assertSee('Büro Beispiel <buero@example.com>')
                ->assertSee('Grüße aus Köln');
        }
    }

    /** AK 3: Skript im HTML-Teil läuft nicht, Fremdbild lädt nicht — sandbox ohne allow-*, eigene CSP. */
    public function test_original_html_is_isolated(): void {
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());

        $page = (string) $this->actingAs($this->owner)->get(route('attachments.mail.show', [$mail, 'html' => 'mail']))
            ->assertOk()
            ->assertSee(__('mail_file.html.hint'))
            ->getContent();
        if (preg_match('#<iframe sandbox="" src="([^"]+)"#', $page, $frame) !== 1) {
            $this->fail('iframe mit leerem sandbox fehlt');
        }
        $this->assertStringNotContainsString('allow-scripts', $page);
        $this->assertStringNotContainsString('allow-same-origin', $page);

        $response = $this->actingAs($this->owner)->get(html_entity_decode($frame[1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', MailAttachmentController::FRAME_CSP);

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringStartsWith('sandbox;', $csp, 'auch direkt geöffnet abgeschottet');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'none'", $csp);
        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertStringNotContainsString('allow-', $csp);
        if (preg_match('/img-src ([^;]+)/', $csp, $img) !== 1) {
            $this->fail('img-src fehlt');
        }
        $this->assertSame('data:', trim($img[1]), 'Fremdbilder gesperrt, nur eingebettete');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('data:image/png;base64,', $body);
        $this->assertStringNotContainsString('refresh', $body);
        $this->assertStringNotContainsString('<link', $body);
        $this->assertStringNotContainsString('<script', $body);

        // Ohne Signatur kein HTML.
        $this->actingAs($this->owner)->get(route('attachments.mail.html', $mail))->assertForbidden();
    }

    /** AK 4: Anhang als Datei am selben Träger übernehmen; danach über die Suche auffindbar. */
    public function test_attachment_can_be_adopted_and_found(): void {
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());

        $this->actingAs($this->owner)->post(route('attachments.mail.adopt', [$mail, '1']))
            ->assertRedirect(route('attachments.mail.show', $mail))
            ->assertSessionHasNoErrors();

        $copy = Attachment::query()->where('id', '!=', $mail->id)->firstOrFail();
        $this->assertSame($this->entry->getMorphClass(), $copy->attachable_type);
        $this->assertSame((int) $this->entry->id, (int) $copy->attachable_id);
        $this->assertSame('Angebot Übergabe.pdf', $copy->original_name);
        $this->assertSame('application/pdf', $copy->mime);
        $this->assertSame(MailFiles::PDF, Storage::disk('local')->get($copy->path));

        $search = app(AttachmentSearchDomain::class);
        $byName = $search->search($this->owner, (int) $this->owner->organization_id, 'Angebot Übergabe', new SearchDomainFilters, 10);
        $this->assertContains('Angebot Übergabe.pdf', array_column($byName, 'title'));

        // Die Mail selbst über ihren Text (auch aus der eingebetteten Mail) — Link auf die Ansicht.
        $byText = $search->search($this->owner, (int) $this->owner->organization_id, 'Rückruf', new SearchDomainFilters, 10);
        $this->assertSame(['angebot.eml'], array_column($byText, 'title'));
        $this->assertSame(route('attachments.mail.show', $mail), $byText[0]['url']);
    }

    public function test_adopt_runs_the_upload_checks(): void {
        $mail = $this->storeMail('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'));

        // notiz.txt in der eingebetteten Mail ist erlaubt; die eingebettete Mail selbst ist keine Datei.
        $this->actingAs($this->owner)->post(route('attachments.mail.adopt', [$mail, '2.0']))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('attachments.mail.adopt', [$mail, '2']))->assertNotFound();

        // Programmdatei in einer Mail: dieselbe Positivliste wie ein Upload.
        $exe = "From: a@example.org\r\nSubject: Werkzeug\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\nSiehe Anhang.\r\n"
            . "--b\r\nContent-Type: application/octet-stream; name=\"rechnung.pdf.exe\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"rechnung.pdf.exe\"\r\n\r\n"
            . base64_encode("MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00" . str_repeat("\x00", 64)) . "\r\n--b--\r\n";
        $tool = $this->storeMail('werkzeug.eml', $exe);
        $this->actingAs($this->owner)->post(route('attachments.mail.adopt', [$tool, '0']))
            ->assertSessionHasErrors(['file' => 'rechnung.pdf.exe: ' . __('Dateityp nicht erlaubt.')]);
        $this->assertSame(0, Attachment::query()->where('original_name', 'rechnung.pdf.exe')->count());
    }

    public function test_visibility_follows_the_carrier(): void {
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());
        $part = MailAttachmentController::partUrl($mail, '1');
        $html = MailAttachmentController::htmlUrl($mail);

        // Gleiche Organisation, aber kein Recht am Auftrag.
        $stranger = User::factory()->user()->create(['organization_id' => $this->owner->organization_id]);
        $this->actingAs($stranger)->get(route('attachments.mail.show', $mail))->assertForbidden();
        $this->actingAs($stranger)->get($part)->assertForbidden();
        $this->actingAs($stranger)->get($html)->assertForbidden();
        $this->actingAs($stranger)->post(route('attachments.mail.adopt', [$mail, '1']))->assertForbidden();

        // Andere Organisation: der Anhang existiert für sie nicht.
        $foreign = User::factory()->admin()->create();
        $this->actingAs($foreign)->get(route('attachments.mail.show', $mail))->assertNotFound();
        $this->actingAs($foreign)->get($part)->assertNotFound();

        // Darf sehen, aber nicht bearbeiten: Ansicht ja, Übernahme nein.
        $reader = User::factory()->buchhaltung()->create(['organization_id' => $this->owner->organization_id]);
        $reader->givePermissionTo(Permission::DiaryViewAny->value);
        $this->actingAs($reader)->get(route('attachments.mail.show', $mail))
            ->assertOk()
            ->assertDontSee(__('mail_file.attachments.adopt'));
        $this->actingAs($reader)->get($part)->assertOk();
        $this->actingAs($reader)->post(route('attachments.mail.adopt', [$mail, '1']))->assertForbidden();
        $this->assertSame(1, Attachment::query()->count());
    }

    public function test_part_paths_stay_inside_the_mail(): void {
        $mail = $this->storeMail('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'));
        $this->actingAs($this->owner);

        foreach (['9', '2', '0.0', '2.0.0.0', '00000'] as $path) {
            $this->get(MailAttachmentController::partUrl($mail, $path))->assertNotFound();
        }
        $this->get(route('attachments.mail.show', [$mail, 'vorschau' => '../../1']))->assertOk()->assertDontSee('<iframe', false);

        // Ein anderer Anhang ist keine Mail.
        $pdf = Attachment::factory()->create([
            'attachable_type' => $this->entry->getMorphClass(),
            'attachable_id' => $this->entry->id,
            'organization_id' => $this->entry->organization_id,
            'original_name' => 'beleg.pdf',
            'mime' => 'application/pdf',
        ]);
        $this->get(route('attachments.mail.show', $pdf))->assertNotFound();
        $this->get(MailAttachmentController::partUrl($pdf, '0'))->assertNotFound();
    }

    public function test_other_attachments_download_with_sandbox(): void {
        $mail = $this->storeMail('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'));

        $response = $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '2.0', true))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Security-Policy', MailAttachmentController::DOWNLOAD_CSP)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('attachment; filename=notiz.txt', $response->headers->get('Content-Disposition'));

        // PDF ohne Vorschau-Wunsch: ebenfalls Download.
        $pdf = $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '0'))->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''Angebot%20%C3%9Cbergabe.pdf", (string) $pdf->headers->get('Content-Disposition'));
    }

    public function test_unreadable_mail_shows_a_message_instead_of_an_error_page(): void {
        Storage::disk('local')->put('attachments/kaputt.msg', substr(MailFiles::fixture('rueckfrage-unicode.msg'), 0, 1500));
        $broken = Attachment::factory()->create([
            'attachable_type' => $this->entry->getMorphClass(),
            'attachable_id' => $this->entry->id,
            'organization_id' => $this->entry->organization_id,
            'user_id' => $this->owner->id,
            'disk' => 'local',
            'path' => 'attachments/kaputt.msg',
            'original_name' => 'kaputt.msg',
            'mime' => 'application/vnd.ms-outlook',
            'size' => 1500,
        ]);

        $this->actingAs($this->owner)->get(route('attachments.mail.show', $broken))
            ->assertOk()
            ->assertSee(__('mail_file.unreadable.title'))
            ->assertSee(__('mail_file.failure.malformed'))
            ->assertSee(__('mail_file.download_original'));
    }

    public function test_attachment_section_links_the_mail_view(): void {
        $mail = $this->storeMail('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'));

        $this->actingAs($this->owner)->get(route('diary.show', $this->entry))
            ->assertOk()
            ->assertSee(route('attachments.mail.show', $mail), false)
            ->assertSee('data-attachment-drop-zone', false);
    }

    /** H1: solange die Lesung läuft, zeigt die Ansicht „wird gelesen“; Anhänge und HTML gibt es noch nicht. */
    public function test_pending_and_aborted_reads(): void {
        Queue::fake();
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());
        Queue::assertPushed(ReadMailAttachmentJob::class, 1);
        Queue::assertPushedOn('media', ReadMailAttachmentJob::class);

        $this->actingAs($this->owner)->get(route('attachments.mail.show', $mail))
            ->assertOk()
            ->assertSee(__('mail_file.unreadable.pending'));
        $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '1'))->assertNotFound();
        $this->actingAs($this->owner)->get(MailAttachmentController::htmlUrl($mail))->assertNotFound();
        // Die Ansicht stößt nichts erneut an.
        Queue::assertPushed(ReadMailAttachmentJob::class, 1);

        // Abbruch des Jobs (Zeitlimit, Absturz): festgehalten, nicht wiederholt.
        (new ReadMailAttachmentJob((int) $mail->id))->failed(null);
        $this->actingAs($this->owner)->get(route('attachments.mail.show', $mail))
            ->assertOk()
            ->assertSee(__('mail_file.failure.aborted'));

        // Eine Lesung, die zu lange offen ist, gilt als abgebrochen.
        $stale = $this->storeMail('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'));
        AttachmentText::query()->where('attachment_id', $stale->id)->update(['created_at' => now()->subMinutes(MailAttachmentContents::PENDING_MINUTES + 1)]);
        $this->actingAs($this->owner)->get(route('attachments.mail.show', $stale))
            ->assertOk()
            ->assertSee(__('mail_file.failure.aborted'));
        Queue::assertPushed(ReadMailAttachmentJob::class, 2);
    }

    /** H1: Ansicht, Download und HTML zerlegen die Mail nicht erneut — sie lesen die eine Extraktion. */
    public function test_requests_never_parse_the_mail_again(): void {
        $mail = $this->storeMail('angebot.eml', MailFiles::eml());
        // Ohne Original: wer jetzt noch lesen wollte, bekäme „fehlt“ statt der Ergebnisse.
        Storage::disk('local')->delete($mail->path);

        $this->actingAs($this->owner)->get(route('attachments.mail.show', [$mail, 'html' => 'mail', 'vorschau' => '1']))
            ->assertOk()
            ->assertSee('Angebot für die Übergabe')
            ->assertDontSee(__('mail_file.failure.missing'));
        $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '1'))->assertOk()->assertContent(MailFiles::PDF);
        $this->actingAs($this->owner)->get(MailAttachmentController::htmlUrl($mail))->assertOk();
        $this->actingAs($this->owner)->get(MailAttachmentController::htmlUrl($mail, '2'))->assertNotFound();
        $this->actingAs($this->owner)->post(route('attachments.mail.adopt', [$mail, '1']))->assertSessionHasNoErrors();
    }

    /** M2: ungeprüfte Typen nur als `.bin` mit Hinweis; Bidi-Zeichen nie im Namen. */
    public function test_unchecked_parts_download_as_bin_with_clean_names(): void {
        $name = "rechnung\u{202E}fdp.exe";
        $exe = "From: a@example.org\r\nSubject: Werkzeug\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\nSiehe Anhang.\r\n"
            . "--b\r\nContent-Type: application/octet-stream\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name) . "\r\n\r\n"
            . base64_encode("MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00" . str_repeat("\x00", 64)) . "\r\n--b--\r\n";
        $mail = $this->storeMail('werkzeug.eml', $exe);

        $page = (string) $this->actingAs($this->owner)->get(route('attachments.mail.show', $mail))
            ->assertOk()
            ->assertSee('rechnungfdp.exe')
            ->assertSee(__('mail_file.attachments.unchecked'))
            ->assertDontSee(__('mail_file.attachments.adopt'))
            ->getContent();
        $this->assertStringNotContainsString("\u{202E}", $page);

        $response = $this->actingAs($this->owner)->get(MailAttachmentController::partUrl($mail, '0', true))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Security-Policy', MailAttachmentController::DOWNLOAD_CSP);
        $this->assertSame('attachment; filename=rechnungfdp.exe.bin', $response->headers->get('Content-Disposition'));
    }

    /** N2: Übernahme nur an Trägern des Upload-Endpunkts. */
    public function test_adopt_is_limited_to_upload_carriers(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->owner->organization_id]);
        $note = \App\Models\Communication\CommunicationNote::factory()->create(['organization_id' => $this->owner->organization_id]);
        $this->assertNotContains($note::class, AttachmentController::TYPE_MAP);
        $mail = app(FileAttacher::class)->storeContent($note, MailFiles::eml(), 'angebot.eml', 'message/rfc822', (int) $admin->id);

        $this->actingAs($admin)->get(route('attachments.mail.show', $mail))->assertOk()->assertDontSee(__('mail_file.attachments.adopt'));
        $this->actingAs($admin)->post(route('attachments.mail.adopt', [$mail, '1']))->assertNotFound();
        $this->assertSame(1, Attachment::query()->count());
    }

    /** N3: die Suche zeigt nur, was die AttachmentPolicy zeigen würde — auch eigene Uploads nicht ohne Sicht auf den Träger. */
    public function test_search_filters_hits_through_the_policy(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->owner->organization_id]);
        Attachment::factory()->create([
            'attachable_type' => $this->entry->getMorphClass(),
            'attachable_id' => $this->entry->id,
            'organization_id' => $this->entry->organization_id,
            'user_id' => $stranger->id,
            'original_name' => 'geheimes-angebot.pdf',
            'mime' => 'application/pdf',
        ]);
        $titles = function (User $user): array {
            /** @var list<array{items: list<array{title: string}>}> $groups */
            $groups = (array) $this->actingAs($user)->get(route('search.index', ['q' => 'geheimes', 'domain' => 'attachments']))->assertOk()->viewData('groups');

            return array_column(array_merge([], ...array_column($groups, 'items')), 'title');
        };

        $this->assertSame([], $titles($stranger));
        $this->assertSame(['geheimes-angebot.pdf'], $titles($this->owner));
    }

    private function storeMail(string $name, string $content): Attachment {
        $this->actingAs($this->owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $this->entry->sqid]), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertSessionHasNoErrors();

        return Attachment::query()->where('original_name', $name)->latest('id')->firstOrFail();
    }
}
