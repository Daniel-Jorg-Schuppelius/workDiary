<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentFileLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\Attachments\Attachment;
use App\Models\Knowledge\KnowledgeArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13: Beim Löschen eines Trägerdatensatzes blieben
 * Anhang-Zeile und Datei liegen. Über die Jahre sammelt sich damit alles an,
 * was je hochgeladen wurde — und ein Löschverlangen nach Artikel 17 lässt sich
 * nicht ehrlich beantworten.
 *
 * Entschieden wurde: erst beim ENDGÜLTIGEN Löschen. Bei einem Soft-Delete
 * bleibt der Anhang, sonst käme ein wiederhergestellter Datensatz ohne seine
 * Dateien zurück.
 */
class AttachmentFileLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function articleWithFile(): array {
        Storage::fake('local');
        $user = $this->orgUser();
        $article = KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $user->id,
        ]);

        Storage::disk('local')->put('attachments/beweis.pdf', 'inhalt');
        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $article->getMorphClass(),
            'attachable_id' => $article->getKey(),
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => 'attachments/beweis.pdf',
            'original_name' => 'beweis.pdf',
            'size' => 7,
        ]);

        return [$article, $attachment];
    }

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_soft_delete_keeps_the_file(): void {
        [$article] = $this->articleWithFile();

        $article->delete();

        Storage::disk('local')->assertExists('attachments/beweis.pdf');
        $this->assertSame(1, Attachment::query()->count(), 'Der Anhang gehoert zum wiederherstellbaren Datensatz.');
    }

    public function test_permanent_delete_takes_the_file_with_it(): void {
        [$article] = $this->articleWithFile();

        $article->forceDelete();

        Storage::disk('local')->assertMissing('attachments/beweis.pdf');
        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_deleting_the_attachment_alone_removes_its_file(): void {
        [, $attachment] = $this->articleWithFile();

        $attachment->delete();

        Storage::disk('local')->assertMissing('attachments/beweis.pdf');
    }
}
