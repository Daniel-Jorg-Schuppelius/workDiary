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
}
