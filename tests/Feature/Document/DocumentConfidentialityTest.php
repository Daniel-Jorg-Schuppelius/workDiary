<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentConfidentialityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Document;

use App\Enums\User\Permission as P;
use App\Models\{Document, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vollaudit 2026-07 (N10): Vertraulichkeitsmerkmal für Dokumente (MVP-031
 * „Rechte für sensible Dokumente") — Muster Kommunikationsnotizen:
 * vertrauliche Dokumente sehen nur Erfasser + document.confidential.manage;
 * Fremdzugriff der Verwalter wird auditiert.
 */
final class DocumentConfidentialityTest extends TestCase {
    use RefreshDatabase;

    private User $creator;
    private Document $document;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');

        $this->creator = User::factory()->user()->create();
        $this->actingAs($this->creator)->post(route('documents.store'), [
            'title' => 'Gehaltsliste Q3',
            'document_type' => \App\Enums\Document\DocumentType::Other->value,
            'confidential' => 1,
            'file' => UploadedFile::fake()->create('gehaltsliste.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $this->document = Document::query()->firstOrFail();
        $this->assertTrue($this->document->confidential);
    }

    public function test_foreign_user_cannot_see_or_open_confidential_document(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->creator->organization_id]);

        $this->actingAs($stranger)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee('Gehaltsliste Q3');

        $this->actingAs($stranger)
            ->get(route('documents.show', $this->document))
            ->assertForbidden();
    }

    public function test_creator_still_sees_own_confidential_document(): void {
        $this->actingAs($this->creator)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Gehaltsliste Q3');

        $this->actingAs($this->creator)
            ->get(route('documents.show', $this->document))
            ->assertOk();

        // Eigenzugriff erzeugt KEINEN Fremdzugriffs-Audit.
        $this->assertDatabaseMissing('audit_logs', ['event' => 'document.confidentialAccessed']);
    }

    public function test_manager_access_is_audited(): void {
        $manager = User::factory()->user()->create(['organization_id' => $this->creator->organization_id]);
        $manager->givePermissionTo(P::DocumentConfidentialManage->value);

        $this->actingAs($manager)
            ->get(route('documents.show', $this->document))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'event' => 'document.confidentialAccessed',
            'auditable_type' => Document::class,
            'auditable_id' => $this->document->id,
        ]);
    }
}
