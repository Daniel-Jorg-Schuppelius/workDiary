<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseFileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Timeline;

use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Attachment, Comment, CommunicationNote, Customer, DiaryEntry, Document, MaterialUsage, OpenIssue, Project, Protocol, TimeEntry, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PDFToolkit\Registries\PDFReaderRegistry;
use Tests\TestCase;

class CaseFileTest extends TestCase {
    use RefreshDatabase;

    public function test_case_file_renders_all_sections(): void {
        $user = User::factory()->user()->create();
        $orgId = (int) $user->organization_id;
        $customer = Customer::factory()->create(['organization_id' => $orgId]);

        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $orgId,
            'customer_id' => $customer->id,
            'title' => 'Heizungswartung Gebäude A',
        ]);

        $project = Project::factory()->create(['organization_id' => $orgId]);
        $timesheet = Timesheet::create([
            'organization_id' => $orgId,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'work_date' => '2030-02-15',
            'status' => TimesheetStatus::Draft->value,
        ]);
        TimeEntry::factory()->create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'diary_entry_id' => $entry->id,
            'timesheet_id' => $timesheet->id,
            'minutes' => 90,
        ]);
        MaterialUsage::create([
            'organization_id' => $orgId,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kupferrohr 15mm',
            'quantity' => '2.5',
            'unit' => 'm',
        ]);
        Protocol::factory()->signed()->create([
            'organization_id' => $orgId,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Abnahmeprotokoll Heizung',
        ]);
        OpenIssue::factory()->create([
            'organization_id' => $orgId,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Dichtung nachbessern',
        ]);
        CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $orgId,
            'created_by_user_id' => $user->id,
            'subject' => 'Telefonat Terminabstimmung',
        ]);
        Document::factory()->create([
            'organization_id' => $orgId,
            'documentable_type' => DiaryEntry::class,
            'documentable_id' => $entry->id,
            'created_by_user_id' => $user->id,
            'title' => 'Wartungsvertrag',
        ]);

        $response = $this->actingAs($user)
            ->get(route('diary.case-file', $entry))
            ->assertOk()
            // Kopf + Pflichtsektionen
            ->assertSee(__('timeline.title.case_file'))
            ->assertSee(__('timeline.case.master_data'))
            ->assertSee(__('timeline.title.section'))
            // Datengetriebene Sektionen
            ->assertSee(__('timeline.case.times'))
            ->assertSee(__('timeline.case.material'))
            ->assertSee(__('timeline.case.protocols'))
            ->assertSee(__('timeline.case.open_issues'))
            ->assertSee(__('timeline.case.communication'))
            ->assertSee(__('timeline.case.documents'))
            // Inhalte
            ->assertSee('Heizungswartung Gebäude A')
            ->assertSee('Kupferrohr 15mm')
            ->assertSee('Abnahmeprotokoll Heizung')
            ->assertSee('Dichtung nachbessern')
            ->assertSee('Telefonat Terminabstimmung')
            ->assertSee('Wartungsvertrag')
            ->assertSee($customer->name)
            // Zeitsumme 1:30 h
            ->assertSee('1:30 h');

        $response->assertSee(__('timeline.action.print'));
    }

    public function test_case_file_hides_empty_sections(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('diary.case-file', $entry))
            ->assertOk()
            ->assertSee(__('timeline.case.master_data'))
            ->assertDontSee(__('timeline.case.protocols'))
            ->assertDontSee(__('timeline.case.open_issues'));
    }

    public function test_case_file_hides_confidential_notes_from_third_users(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();

        CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
            'subject' => 'Vertrauliche Preisabsprache',
        ]);

        $this->actingAs($author)
            ->get(route('diary.case-file', $entry))
            ->assertOk()
            ->assertSee('Vertrauliche Preisabsprache');

        $this->actingAs($other)
            ->get(route('diary.case-file', $entry))
            ->assertOk()
            ->assertDontSee('Vertrauliche Preisabsprache');
    }

    public function test_guest_is_redirected_to_login(): void {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->get(route('diary.case-file', $entry))
            ->assertRedirect(route('login'));
    }

    public function test_cross_org_user_gets_404(): void {
        $owner = User::factory()->user()->create();
        $foreign = User::factory()->user()->create(); // eigene Organisation
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->assertNotSame((int) $owner->organization_id, (int) $foreign->organization_id);

        $this->actingAs($foreign)
            ->get(route('diary.case-file', $entry))
            ->assertNotFound();
    }

    public function test_show_page_links_to_case_file(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee(route('diary.case-file', $entry), false);
    }

    // ── Interne Fallakte als PDF (MVP-349) ────────────────────────────────

    public function test_case_file_pdf_downloads_and_contains_internal_entries(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'title' => 'Heizungswartung Gebaeude A',
        ]);

        // Interner Kommentar + INTERNER Anhang — der Unterschied zum strikt
        // kundensichtbaren Portal-PDF (customer.diary.pdf). Marker bewusst als
        // Ein-Wort-Token, damit PDF-Zeilenumbrüche den Substring nicht trennen.
        Comment::factory()->create([
            'commentable_type' => $entry->getMorphClass(),
            'commentable_id' => $entry->id,
            'user_id' => $user->id,
            'body' => 'Interner-Vermerk-Ersatzteilbestellung',
        ]);
        Attachment::factory()->create([
            'organization_id' => $entry->organization_id,
            'attachable_type' => $entry->getMorphClass(),
            'attachable_id' => $entry->id,
            'original_name' => 'internes-foto.jpg',
            'customer_visible' => false,
        ]);

        $response = $this->actingAs($user)->get(route('diary.case-file.pdf', $entry));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        // Dateiname trägt Auftrag (Sqid) und Datum.
        $this->assertStringContainsString(
            'fallakte-' . $entry->getRouteKey() . '-' . now()->format('Y-m-d') . '.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );

        $text = $this->extractPdfText($response);
        $this->assertStringContainsString('Heizungswartung', $text);
        $this->assertStringContainsString('Interner-Vermerk-Ersatzteilbestellung', $text);
        $this->assertStringContainsString('internes-foto.jpg', $text);
    }

    public function test_case_file_pdf_requires_authentication(): void {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->get(route('diary.case-file.pdf', $entry))
            ->assertRedirect(route('login'));
    }

    public function test_case_file_pdf_is_org_isolated(): void {
        $owner = User::factory()->user()->create();
        $foreign = User::factory()->user()->create(); // eigene Organisation
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->assertNotSame((int) $owner->organization_id, (int) $foreign->organization_id);

        $this->actingAs($foreign)
            ->get(route('diary.case-file.pdf', $entry))
            ->assertNotFound();
    }

    public function test_case_file_page_links_to_pdf_export(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('diary.case-file', $entry))
            ->assertOk()
            ->assertSee(route('diary.case-file.pdf', $entry), false);
    }

    /**
     * Extrahiert den Text eines PDF-Responses über das pdf-toolkit — die
     * Textströme im PDF sind komprimiert, ein Substring-Check auf den
     * Roh-Bytes wäre wertlos (Muster: ReportPdfTenantTest).
     */
    private function extractPdfText(TestResponse $response): string {
        $path = tempnam(sys_get_temp_dir(), 'wd-case-file-') . '.pdf';
        file_put_contents($path, (string) $response->getContent());

        try {
            $document = PDFReaderRegistry::getInstance()->extractTextOnly($path);
            $text = $document->getTextOrDefault();
        } catch (\Throwable $e) {
            $this->fail('PDF-Textextraktion fehlgeschlagen (Reader/pdftotext verfügbar?): ' . $e->getMessage());
        } finally {
            @unlink($path);
        }

        return $text;
    }
}
