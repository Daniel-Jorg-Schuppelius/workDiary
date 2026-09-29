<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCommunicationNotesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Supplier;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Models\Communication\CommunicationNote;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, SubjectDataExporter};
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1023: Kommunikationsnotizen am Lieferanten, auch in der DSGVO-Auskunft. */
final class SupplierCommunicationNotesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function storeNote(User $user, Supplier $supplier, string $subject): CommunicationNote {
        $this->actingAs($user)->post(route('communication-notes.store'), [
            'notable_kind' => 'supplier',
            'notable_id' => Sqid::encode(Supplier::class, (int) $supplier->id),
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Inbound->value,
            'occurred_at' => now()->subHour()->format('Y-m-d H:i'),
            'subject' => $subject,
            'body' => 'Liefertermin auf Freitag verschoben.',
        ])->assertRedirect();

        return CommunicationNote::query()->where('subject', $subject)->sole();
    }

    public function test_notes_are_kept_and_shown_at_the_supplier(): void {
        $admin = $this->orgAdmin();
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stahl KG']);

        $note = $this->storeNote($admin, $supplier, 'Anruf Disposition');
        $this->assertTrue(MorphMap::is($note->notable_type, Supplier::class));
        $this->assertSame(__('entity-types.Supplier'), $note->notableKindLabel());
        $this->assertSame('Stahl KG', $note->notableLabel());

        $this->actingAs($admin)->get(route('suppliers.show', $supplier))->assertOk()
            ->assertSee(__('communication.title.index'))->assertSee('Anruf Disposition');
        $this->actingAs($admin)->get(route('communication-notes.edit', $note))->assertOk()->assertSee('Anruf Disposition');
    }

    public function test_note_kinds_are_labelled_by_their_entity_type(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $note = new CommunicationNote(['notable_type' => $project->getMorphClass(), 'notable_id' => $project->id]);

        $this->assertSame(__('entity-types.Project'), $note->notableKindLabel());
    }

    public function test_the_access_request_of_a_supplier_lists_the_notes(): void {
        DataProtectionPermissions::seedOrganization($this->organization);
        $officer = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Einzelhändler Weber']);
        $this->storeNote($this->orgAdmin(), $supplier, 'Zusage Nachlieferung');

        $dsr = app(DataSubjectRequestService::class)->open($this->organization, DataSubjectRequestType::Access, 'Weber', 'Auskunft bitte.', null, $officer);
        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::Supplier, $exporter->resolve(DataSubjectKind::Supplier, (int) $this->organization->id, (int) $supplier->id));

        $sections = array_column($payload['sections'], null, 'key');
        $this->assertArrayHasKey('communication', $sections);
        $notes = collect($sections['communication']['families'])->firstWhere('table', 'communication_notes');
        $this->assertSame(1, $notes['count']);
    }
}
