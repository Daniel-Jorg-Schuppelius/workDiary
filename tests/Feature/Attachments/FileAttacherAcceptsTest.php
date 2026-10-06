<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileAttacherAcceptsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Attachments;

use App\Services\Attachments\FileAttacher;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Die eine Positivliste der Datei-Uploads (Konsolidierungs-Audit 2026-10,
 * k3-7): Endung und der am Inhalt erkannte Typ müssen beide passen.
 */
final class FileAttacherAcceptsTest extends TestCase {
    public function test_extension_and_detected_type_must_both_be_listed(): void {
        $this->assertTrue(FileAttacher::accepts(UploadedFile::fake()->create('rechnung.pdf', 10, 'application/pdf')));
        $this->assertTrue(FileAttacher::accepts(UploadedFile::fake()->create('LISTE.CSV', 1, 'text/csv')));

        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->create('shell.php', 1, 'text/plain')));
        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->create('getarnt.pdf', 1, 'text/html')));
        $this->assertFalse(FileAttacher::accepts(UploadedFile::fake()->create('alt.doc', 1, 'application/msword')));
    }

    public function test_every_listed_extension_has_a_listed_type(): void {
        $this->assertNotContains('php', FileAttacher::ALLOWED_EXTENSIONS);
        $this->assertContains('application/pdf', FileAttacher::ALLOWED_MIMES);
        $this->assertSame(array_values(array_unique(FileAttacher::ALLOWED_MIMES)), FileAttacher::ALLOWED_MIMES);
    }
}
