<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UploadExtensionGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\KnowledgeArticle;
use App\Services\Attachments\FileAttacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (files-upload-1): Die gespeicherte Endung kam
 * aus dem Dateinamen des Clients. Eine „notiz.pdf.php" lag damit als `.php` in
 * der privaten Ablage — harmlos erst, solange kein zweiter Fehler dazukommt.
 */
final class UploadExtensionGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_stored_extension_comes_from_the_detected_type(): void {
        Storage::fake('local');
        $user = $this->orgUser();
        $article = KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('notiz.pdf.php', '%PDF-1.4 Testinhalt');

        $attachment = app(FileAttacher::class)->store($article, $file, (int) $user->id, [
            'organization_id' => $this->organization->id,
        ]);

        $this->assertStringEndsNotWith('.php', (string) $attachment->path);
        // Der ursprüngliche Name bleibt für die Anzeige erhalten (bereinigt).
        $this->assertStringContainsString('notiz', (string) $attachment->original_name);
    }

    /**
     * Dieselbe Regel für Roh-Inhalte (Mail-Intake, Ticket-Antworten): der
     * Name kommt dort vom Absender.
     */
    public function test_store_content_takes_the_extension_from_the_content(): void {
        Storage::fake('local');
        $user = $this->orgUser();
        $article = KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $user->id,
        ]);
        $attacher = app(FileAttacher::class);
        $extra = ['organization_id' => $this->organization->id];

        $script = $attacher->storeContent($article, "<?php system(\$_GET['c']);", 'rechnung.pdf.php', 'application/pdf', (int) $user->id, $extra);
        $this->assertStringEndsWith('.bin', (string) $script->path);
        $this->assertStringContainsString('rechnung', (string) $script->original_name);
        // Auch der Typ kommt vom Inhalt, nicht vom Absender (Code-Quality-Befund 2026-09-18).
        $this->assertNotSame('application/pdf', $script->mime);

        $video = $attacher->storeContent($article, "%PDF-1.7\n%%EOF", 'clip.mp4', 'video/mp4', (int) $user->id, $extra);
        $this->assertSame('application/pdf', $video->mime);

        $pdf = $attacher->storeContent($article, "%PDF-1.7\n%%EOF", 'scan.php', null, (int) $user->id, $extra);
        $this->assertStringEndsWith('.pdf', (string) $pdf->path);

        $empty = $attacher->storeContent($article, '', 'leer.txt', 'text/plain', (int) $user->id, $extra);
        $this->assertStringEndsWith('.bin', (string) $empty->path);
        $this->assertSame('text/plain', $empty->mime, 'Ohne erkennbaren Inhalt bleibt die Angabe als Hinweis.');
    }
}
