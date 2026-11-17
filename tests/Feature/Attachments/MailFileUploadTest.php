<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileUploadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Attachments;

use App\Enums\Attachments\{MailFileFailure, UploadPurpose};
use App\Models\Attachments\{Attachment, AttachmentText};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Attachments\Demo\MailFileDemoBlock;
use App\Services\Attachments\FileAttacher;
use App\Services\Attachments\Mail\MailAttachmentContents;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Document\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MailFiles;
use Tests\TestCase;

/**
 * Upload-Weg für Mail-Dateien (Feature 204, MVP-1653): nur auf internen
 * Flächen, Endung und am Inhalt erkannter Typ müssen zusammenpassen, beim
 * Hochladen nur eine billige Vorprüfung — gelesen wird einmal im Hintergrund.
 */
final class MailFileUploadTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_msg_and_eml_are_accepted_by_content_on_internal_surfaces(): void {
        $internal = UploadPurpose::Internal;
        $this->assertNull(FileAttacher::rejection(UploadedFile::fake()->createWithContent('Rückfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg')), $internal));
        $this->assertNull(FileAttacher::rejection(UploadedFile::fake()->createWithContent('angebot.EML', MailFiles::eml()), $internal));
        // Fileinfo meldet eine Mail, die mit MIME-Version beginnt, als Text — die Vorprüfung entscheidet.
        $this->assertNull(FileAttacher::rejection(UploadedFile::fake()->createWithContent('frage.eml', "MIME-Version: 1.0\r\n" . MailFiles::plainEml()), $internal));
        $this->assertContains('msg', UploadPurpose::Internal->extensions());
        $this->assertNotContains('msg', FileAttacher::ALLOWED_EXTENSIONS, 'Stellen mit getrennter Prüfung nehmen keine Mails an');
    }

    /** M2: Portale, Auskunftsportal, Helpdesk-Portal und Kundeneingang nehmen keine Mails — und lesen nichts davon. */
    public function test_external_purposes_reject_mail_files_without_reading_them(): void {
        // Diese Mail scheitert an der Vorprüfung — kommt die Endungsmeldung, wurde nichts gelesen.
        $costly = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"\"\r\n\r\n--\r\n";
        $this->assertSame(MailFileFailure::Malformed->message(), FileAttacher::rejection(UploadedFile::fake()->createWithContent('a.eml', $costly), UploadPurpose::Internal));
        $this->assertSame((string) __('Dateityp nicht erlaubt.'), FileAttacher::rejection(UploadedFile::fake()->createWithContent('a.eml', $costly)));

        $this->assertNotContains('eml', UploadPurpose::General->extensions());
        $this->assertSame((string) __('Dateityp nicht erlaubt.'), FileAttacher::rejection(UploadedFile::fake()->createWithContent('angebot.eml', MailFiles::eml())));
        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->createWithContent('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg'))));
        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->createWithContent('angebot.eml', MailFiles::eml()), UploadPurpose::PrintData));
    }

    public function test_compound_files_that_are_no_outlook_message_are_rejected(): void {
        $internal = UploadPurpose::Internal;
        $doc = UploadedFile::fake()->createWithContent('rechnung.msg', MailFiles::fixture('word-dokument.doc'));
        $ole = UploadedFile::fake()->createWithContent('ablage.msg', MailFiles::fixture('ole-ohne-nachricht.bin'));
        $pdf = UploadedFile::fake()->createWithContent('getarnt.msg', MailFiles::PDF);

        $this->assertSame((string) __('Dateityp nicht erlaubt.'), FileAttacher::rejection($doc, $internal));
        $this->assertSame((string) __('Dateityp nicht erlaubt.'), FileAttacher::rejection($ole, $internal), 'unspezifisches Compound File');
        $this->assertSame((string) __('Dateityp nicht erlaubt.'), FileAttacher::rejection($pdf, $internal));
        // Das Word-Dokument unter seiner eigenen Endung bleibt verboten (keine .doc auf der Liste).
        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->createWithContent('rechnung.doc', MailFiles::fixture('word-dokument.doc')), $internal));
    }

    public function test_eml_without_mail_headers_or_under_foreign_extension_is_rejected(): void {
        $internal = UploadPurpose::Internal;
        $this->assertNotNull(FileAttacher::rejection(UploadedFile::fake()->createWithContent('notiz.eml', "<html><body>kein Kopf</body></html>"), $internal));
        // Mail-Inhalt unter fremder Endung: der Typ steht nicht auf der flachen Liste.
        $this->assertFalse(FileAttacher::accepts(MailFiles::upload('angebot.txt', MailFiles::fixture('rueckfrage-unicode.msg')), $internal));
        $this->assertFalse(FileAttacher::accepts(MailFiles::upload('angebot.pdf', MailFiles::eml()), $internal));
    }

    /** H1: ein präpariertes .eml bindet den Upload nicht — es scheitert an der linearen Vorprüfung. */
    public function test_crafted_eml_uploads_fail_fast(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        $folded = "From: a@example.org\r\nSubject: x\r\nContent-Type:\r\n multipart/mixed; boundary=\"b\"\r\n\r\n--b\r\nX: y\r\n"
            . str_repeat("aaaaaaaaaa\r\n", 200_000) . "--b--\r\n";
        $to = 'To: ' . implode(', ', array_fill(0, 18_000, 'a@b.c'));
        $addresses = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n"
            . str_repeat("--b\r\n{$to}\r\nContent-Type: text/plain\r\n\r\nText\r\n", 4) . "--b--\r\n";

        foreach (['gefaltet.eml' => $folded, 'adressen.eml' => $addresses] as $name => $eml) {
            $started = microtime(true);
            $this->actingAs($owner)
                ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->sqid]), ['file' => MailFiles::upload($name, $eml)])
                ->assertSessionHasErrors(['file' => MailFileFailure::TooComplex->message()]);
            $this->assertLessThan(3.0, microtime(true) - $started, $name);
        }
        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_upload_stores_the_mail_and_reads_it_once_in_the_background(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->sqid]), [
                'file' => UploadedFile::fake()->createWithContent('Rückfrage Übergabe.msg', MailFiles::fixture('rueckfrage-unicode.msg')),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->firstOrFail();
        $this->assertSame('Rückfrage Übergabe.msg', $attachment->original_name);
        $this->assertSame('application/vnd.ms-outlook', $attachment->mime);
        $this->assertStringEndsWith('.msg', $attachment->path);
        $this->assertNotNull($attachment->mailFormat());

        // Die Lesung lief (Warteschlange in Tests synchron): Suchtext, Zusammenfassung, Teile.
        $text = AttachmentText::query()->where('attachment_id', $attachment->id)->firstOrFail();
        $this->assertSame((int) $attachment->organization_id, (int) $text->organization_id);
        $this->assertNull($text->failure_reason);
        $this->assertNotNull($text->extracted_at);
        $this->assertStringContainsString('Rückfrage zur Übergabe', (string) $text->text);
        $this->assertStringContainsString('erika.mustermann@example.org', (string) $text->text);
        $this->assertStringContainsString('Grüße aus Köln', (string) $text->text);
        $this->assertSame('Rückfrage zur Übergabe – Größe & Maße', $text->message()?->subject);
        Storage::disk('local')->assertExists((string) $text->parts_path . '/part-0.bin');
        Storage::disk('local')->assertExists((string) $text->parts_path . '/html-mail.html');

        // Löschen des Anhangs nimmt die extrahierten Teile mit.
        $parts = (string) $text->parts_path;
        $attachment->delete();
        Storage::disk('local')->assertMissing($parts . '/part-0.bin');
        $this->assertSame(0, AttachmentText::query()->count());
    }

    /** H1: die Vorprüfung ist billig; was erst beim Lesen scheitert, landet als Grund in der Zeile. */
    public function test_unreadable_mail_is_accepted_and_ends_as_failure_without_retry(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        config(['mail_files.max_attachments' => 1]);
        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->sqid]), [
                'file' => UploadedFile::fake()->createWithContent('anfrage.msg', MailFiles::fixture('rueckfrage-unicode.msg')),
            ])
            ->assertSessionHasNoErrors();
        $attachment = Attachment::query()->sole();
        $text = AttachmentText::query()->sole();
        $this->assertSame(MailFileFailure::TooManyAttachments, $text->failure_reason);
        $this->assertNull($text->parts_path);

        // Kein zweites Lesen: ohne Original bliebe es sonst nicht beim ersten Grund.
        Storage::disk('local')->delete($attachment->path);
        app(MailAttachmentContents::class)->queue($attachment);
        $state = app(MailAttachmentContents::class)->state($attachment);
        $this->assertSame('failed', $state['state']);
        $this->assertSame(MailFileFailure::TooManyAttachments, $state['failure']);

        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->sqid]), [
                'file' => UploadedFile::fake()->createWithContent('vertrag.msg', MailFiles::fixture('word-dokument.doc')),
            ])
            ->assertSessionHasErrors(['file' => (string) __('Dateityp nicht erlaubt.')]);
    }

    public function test_demo_block_seeds_a_readable_mail_and_purges_only_its_own(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        $organization = $owner->organization;
        $this->assertNotNull($organization);
        $context = new DemoSeedContext($organization, $owner, collect([$owner]), [], null, null, $entry);
        $block = app(MailFileDemoBlock::class);

        // Ein echter Anhang gleichen Namens bleibt beim Aufräumen unberührt (N5).
        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->sqid]), ['file' => UploadedFile::fake()->createWithContent(MailFileDemoBlock::FILE_NAME, MailFiles::eml())])
            ->assertSessionHasNoErrors();
        $real = Attachment::query()->sole();

        $this->assertTrue($block->supports($context));
        $this->assertSame(['mail_files' => 1], $block->seed($context));
        $demo = Attachment::query()->whereKeyNot($real->id)->sole();
        $this->assertStringStartsWith(MailFileDemoBlock::FOLDER . '/', $demo->path);
        $this->assertSame((string) __('mail_file.demo.subject'), app(MailAttachmentContents::class)->message($demo)?->subject);

        $block->purge($organization);
        $this->assertSame([(int) $real->id], Attachment::query()->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        Storage::disk('local')->assertMissing($demo->path);
    }

    public function test_document_text_extractor_reads_mail_files(): void {
        $text = app(DocumentTextExtractor::class)->extractFile(MailFiles::fixturePath('rueckfrage-ansi.msg'), 'Rückfrage.msg', 'application/vnd.ms-outlook');

        $this->assertStringContainsString('Rückfrage zur Übergabe – Größe & Maße', $text);
        $this->assertStringContainsString('Erika Mustermann', $text);
        $this->assertStringContainsString('anbei das Angebot', $text);
    }
}
